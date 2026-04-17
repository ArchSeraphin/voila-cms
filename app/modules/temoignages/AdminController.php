<?php
declare(strict_types=1);
namespace App\Modules\Temoignages;

use App\Core\{Container, Request, Response, Session, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/temoignages/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/temoignages/form.html.twig', [
            'r' => ['id'=>null,'auteur'=>'','entreprise'=>'','photo'=>'','citation'=>'','note'=>0,'ordre'=>0,'published'=>1],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/temoignages/new');
        Model::insert($data);
        Session::flash('success', 'Témoignage créé.');
        return Response::redirect('/admin/temoignages');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/temoignages/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/temoignages/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Témoignage mis à jour.');
        return Response::redirect('/admin/temoignages');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Témoignage supprimé.');
        return Response::redirect('/admin/temoignages');
    }

    /** @return array<string,mixed>|null */
    private function formData(Request $req): ?array
    {
        $auteur = trim((string)$req->post('auteur', ''));
        $citation = trim((string)$req->post('citation', ''));
        if ($auteur === '' || $citation === '') {
            Session::flash('error', "L'auteur et la citation sont obligatoires.");
            return null;
        }
        $note = (int)$req->post('note', 0);
        return [
            'auteur'     => $auteur,
            'entreprise' => $this->nullIfEmpty($req->post('entreprise')),
            'photo'      => $this->nullIfEmpty($req->post('photo')),
            'citation'   => $citation,
            'note'       => ($note >= 1 && $note <= 5) ? $note : null,
            'ordre'      => (int)$req->post('ordre', 0),
            'published'  => $req->post('published') === '1' ? 1 : 0,
        ];
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }
}
