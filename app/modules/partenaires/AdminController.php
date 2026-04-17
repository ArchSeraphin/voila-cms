<?php
declare(strict_types=1);
namespace App\Modules\Partenaires;

use App\Core\{Container, Request, Response, Session, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/partenaires/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/partenaires/form.html.twig', [
            'r' => ['id' => null, 'nom' => '', 'logo' => '', 'url' => '', 'description' => '', 'ordre' => 0, 'published' => 1],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/partenaires/new');
        Model::insert($data);
        Session::flash('success', 'Partenaire créé.');
        return Response::redirect('/admin/partenaires');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/partenaires/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/partenaires/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Partenaire mis à jour.');
        return Response::redirect('/admin/partenaires');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Partenaire supprimé.');
        return Response::redirect('/admin/partenaires');
    }

    /** @return array<string,mixed>|null */
    private function formData(Request $req): ?array
    {
        $nom = trim((string)$req->post('nom', ''));
        if ($nom === '') {
            Session::flash('error', 'Le nom est obligatoire.');
            return null;
        }
        return [
            'nom'         => $nom,
            'logo'        => $this->nullIfEmpty($req->post('logo')),
            'url'         => $this->nullIfEmpty($req->post('url')),
            'description' => $this->nullIfEmpty($req->post('description')),
            'ordre'       => (int)$req->post('ordre', 0),
            'published'   => $req->post('published') === '1' ? 1 : 0,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
