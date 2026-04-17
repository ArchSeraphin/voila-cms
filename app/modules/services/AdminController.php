<?php
declare(strict_types=1);
namespace App\Modules\Services;

use App\Core\{Container, Request, Response, Session, Slug, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/services/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/services/form.html.twig', [
            'r' => [
                'id'=>null,'titre'=>'','slug'=>'','icone'=>'',
                'description_courte'=>'','contenu'=>'','image'=>'',
                'ordre'=>0,'published'=>1,'seo_title'=>'','seo_description'=>'',
            ],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/services/new');
        Model::insert($data);
        Session::flash('success', 'Service créé.');
        return Response::redirect('/admin/services');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/services/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/services/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Service mis à jour.');
        return Response::redirect('/admin/services');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Service supprimé.');
        return Response::redirect('/admin/services');
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
        return [
            'titre'              => $titre,
            'slug'               => $slug,
            'icone'              => $this->nullIfEmpty($req->post('icone')),
            'description_courte' => $this->nullIfEmpty($req->post('description_courte')),
            'contenu'            => $this->nullIfEmpty($req->post('contenu')),
            'image'              => $this->nullIfEmpty($req->post('image')),
            'ordre'              => (int)$req->post('ordre', 0),
            'published'          => $req->post('published') === '1' ? 1 : 0,
            'seo_title'          => $this->nullIfEmpty($req->post('seo_title')),
            'seo_description'    => $this->nullIfEmpty($req->post('seo_description')),
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
