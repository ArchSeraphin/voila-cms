<?php
declare(strict_types=1);
namespace App\Modules\Equipe;

use App\Core\{Container, Request, Response, Session, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/equipe/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/equipe/form.html.twig', [
            'r' => ['id'=>null,'nom'=>'','fonction'=>'','photo'=>'','bio'=>'','linkedin'=>'','ordre'=>0,'published'=>1],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/equipe/new');
        Model::insert($data);
        Session::flash('success', 'Membre créé.');
        return Response::redirect('/admin/equipe');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/equipe/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/equipe/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Membre mis à jour.');
        return Response::redirect('/admin/equipe');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Membre supprimé.');
        return Response::redirect('/admin/equipe');
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
            'nom'       => $nom,
            'fonction'  => $this->nullIfEmpty($req->post('fonction')),
            'photo'     => $this->nullIfEmpty($req->post('photo')),
            'bio'       => $this->nullIfEmpty($req->post('bio')),
            'linkedin'  => $this->nullIfEmpty($req->post('linkedin')),
            'ordre'     => (int)$req->post('ordre', 0),
            'published' => $req->post('published') === '1' ? 1 : 0,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
