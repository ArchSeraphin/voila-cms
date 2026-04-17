<?php
declare(strict_types=1);
namespace App\Modules\Faq;

use App\Core\{Container, Request, Response, Session, View};

final class AdminController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/faq/list.html.twig', [
            'rows' => Model::listAll(),
        ]));
    }

    /** @param array<string,mixed> $params */
    public function new(Request $req, array $params): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/faq/form.html.twig', [
            'r' => ['id'=>null,'question'=>'','reponse'=>'','categorie'=>'','ordre'=>0,'published'=>1],
        ]));
    }

    /** @param array<string,mixed> $params */
    public function create(Request $req, array $params): Response
    {
        $data = $this->formData($req);
        if ($data === null) return Response::redirect('/admin/faq/new');
        Model::insert($data);
        Session::flash('success', 'Question créée.');
        return Response::redirect('/admin/faq');
    }

    /** @param array<string,mixed> $params */
    public function edit(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $row = Model::findById($id);
        if (!$row) return Response::notFound();
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/modules/faq/form.html.twig', ['r' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function update(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if (!Model::findById($id)) return Response::notFound();
        $data = $this->formData($req);
        if ($data === null) return Response::redirect("/admin/faq/{$id}/edit");
        Model::update($id, $data);
        Session::flash('success', 'Question mise à jour.');
        return Response::redirect('/admin/faq');
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        Model::delete((int)$params['id']);
        Session::flash('success', 'Question supprimée.');
        return Response::redirect('/admin/faq');
    }

    /** @return array<string,mixed>|null */
    private function formData(Request $req): ?array
    {
        $question = trim((string)$req->post('question', ''));
        $reponse = trim((string)$req->post('reponse', ''));
        if ($question === '' || $reponse === '') {
            Session::flash('error', 'La question et la réponse sont obligatoires.');
            return null;
        }
        return [
            'question'  => $question,
            'reponse'   => $reponse,
            'categorie' => $this->nullIfEmpty($req->post('categorie')),
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
