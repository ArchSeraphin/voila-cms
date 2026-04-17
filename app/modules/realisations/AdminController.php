<?php
declare(strict_types=1);
namespace App\Modules\Realisations;

use App\Core\{Container, Request, Response, Session, Slug, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/realisations/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $blank = [
            'id'=>null,'titre'=>'','slug'=>'','client'=>'',
            'date_realisation'=>'','categorie'=>'','description'=>'',
            'cover_image'=>'','gallery_json'=>'[]',
            'published'=>1,'seo_title'=>'','seo_description'=>'',
        ];
        return new Response($view->render('admin/modules/realisations/form.html.twig', [
            'r' => $blank,
            'gallery_paths' => '',
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/realisations/new');
        Model::insert($data);
        Session::flash('success', 'Réalisation créée.');
        return Response::redirect('/admin/realisations');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        $gallery = json_decode((string)($row['gallery_json'] ?? '[]'), true) ?: [];
        $row['date_realisation'] = (string)($row['date_realisation'] ?? '');
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/realisations/form.html.twig', [
            'r' => $row,
            'gallery_paths' => implode("\n", $gallery),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/realisations/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Réalisation mise à jour.');
        return Response::redirect('/admin/realisations');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Réalisation supprimée.');
        return Response::redirect('/admin/realisations');
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

        // Gallery: newline-separated paths → JSON array
        $raw = (string)$req->post('gallery', '');
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw))));
        $galleryJson = json_encode($lines, JSON_UNESCAPED_SLASHES) ?: '[]';

        $date = trim((string)$req->post('date_realisation', ''));

        return [
            'titre'            => $titre,
            'slug'             => $slug,
            'client'           => $this->nullIfEmpty($req->post('client')),
            'date_realisation' => $date === '' ? null : $date,
            'categorie'        => $this->nullIfEmpty($req->post('categorie')),
            'description'      => $this->nullIfEmpty($req->post('description')),
            'cover_image'      => $this->nullIfEmpty($req->post('cover_image')),
            'gallery_json'     => $galleryJson,
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
