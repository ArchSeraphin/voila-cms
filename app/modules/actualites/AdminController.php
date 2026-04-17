<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\{Container, Paginator, Request, Response, Session, Slug, View};

final class AdminController
{
    private const PER_PAGE = 20;

    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $page = max(1, (int)$req->query('page', 1));
        $total = Model::countAll();
        $pg = new Paginator($total, self::PER_PAGE, $page);
        $rows = Model::listAll(self::PER_PAGE, $pg->offset);

        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/actualites/list.html.twig', [
            'rows'      => $rows,
            'paginator' => $pg,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $blank = [
            'id' => null, 'titre' => '', 'slug' => '', 'image' => '',
            'date_publication' => date('Y-m-d\TH:i'),
            'extrait' => '', 'contenu' => '',
            'published' => 0, 'seo_title' => '', 'seo_description' => '',
        ];
        return new Response($view->render('admin/modules/actualites/form.html.twig', ['r' => $blank]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/actualites/new');
        Model::insert($data);
        Session::flash('success', 'Actualité créée.');
        return Response::redirect('/admin/actualites');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        $row['date_publication'] = str_replace(' ', 'T', substr((string)$row['date_publication'], 0, 16));
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/actualites/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/actualites/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Actualité mise à jour.');
        return Response::redirect('/admin/actualites');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        Model::delete($id);
        Session::flash('success', 'Actualité supprimée.');
        return Response::redirect('/admin/actualites');
    }

    /** @return array<string,mixed>|null */
    private function formData(Request $req): ?array
    {
        $titre = trim((string)$req->post('titre', ''));
        if ($titre === '') {
            Session::flash('error', 'Le titre est obligatoire.');
            return null;
        }
        $slug = trim((string)$req->post('slug', ''));
        if ($slug === '') $slug = Slug::make($titre);
        $date = (string)$req->post('date_publication', '');
        $date = str_replace('T', ' ', $date);
        if (strlen($date) === 16) $date .= ':00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
            $date = date('Y-m-d H:i:s');
        }
        return [
            'titre'            => $titre,
            'slug'             => $slug,
            'date_publication' => $date,
            'image'            => $this->nullIfEmpty($req->post('image')),
            'extrait'          => $this->nullIfEmpty($req->post('extrait')),
            'contenu'          => $this->nullIfEmpty($req->post('contenu')),
            'published'        => $req->post('published') === '1' ? 1 : 0,
            'seo_title'        => $this->nullIfEmpty($req->post('seo_title')),
            'seo_description'  => $this->nullIfEmpty($req->post('seo_description')),
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
