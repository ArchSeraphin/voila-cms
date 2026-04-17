<?php
declare(strict_types=1);
namespace App\Modules\Documents;

use App\Core\{Container, Request, Response, Session, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/documents/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/documents/form.html.twig', [
            'r' => ['id'=>null,'titre'=>'','fichier_path'=>'','categorie'=>'','date_document'=>'','ordre'=>0,'published'=>1],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/documents/new');
        Model::insert($data);
        Session::flash('success', 'Document créé.');
        return Response::redirect('/admin/documents');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/documents/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/documents/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Document mis à jour.');
        return Response::redirect('/admin/documents');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Document supprimé.');
        return Response::redirect('/admin/documents');
    }

    /** @return array<string,mixed>|null */
    private function formData(Request $req): ?array
    {
        $titre = trim((string)$req->post('titre', ''));
        $fichier = trim((string)$req->post('fichier_path', ''));
        if ($titre === '') {
            Session::flash('error', 'Le titre est obligatoire.');
            return null;
        }
        if ($fichier === '') {
            Session::flash('error', 'Veuillez uploader un fichier PDF.');
            return null;
        }
        $date = trim((string)$req->post('date_document', ''));
        return [
            'titre'         => $titre,
            'fichier_path'  => $fichier,
            'categorie'     => $this->nullIfEmpty($req->post('categorie')),
            'date_document' => $date === '' ? null : $date,
            'ordre'         => (int)$req->post('ordre', 0),
            'published'     => $req->post('published') === '1' ? 1 : 0,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
