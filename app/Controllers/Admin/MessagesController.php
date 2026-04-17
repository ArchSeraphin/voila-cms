<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Container, DB, Request, Response, Session, View};

final class MessagesController
{
    /** @param array<string,mixed> $params */
    public function index(Request $req, array $params): Response
    {
        $rows = DB::conn()->query(
            "SELECT id, nom, email, sujet, SUBSTRING(message, 1, 100) AS preview, read_at, created_at
             FROM contact_messages ORDER BY created_at DESC LIMIT 200"
        )->fetchAll() ?: [];
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/messages/list.html.twig', [
            'rows' => $rows,
        ]));
    }

    /** @param array<string,mixed> $params */
    public function show(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $stmt = DB::conn()->prepare("SELECT * FROM contact_messages WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return Response::notFound();
        if ($row['read_at'] === null) {
            DB::conn()->prepare("UPDATE contact_messages SET read_at=NOW() WHERE id=?")->execute([$id]);
        }
        /** @var View $view */
        $view = Container::get(View::class);
        return new Response($view->render('admin/messages/show.html.twig', ['row' => $row]));
    }

    /** @param array<string,mixed> $params */
    public function destroy(Request $req, array $params): Response
    {
        DB::conn()->prepare("DELETE FROM contact_messages WHERE id=?")->execute([(int)($params['id'] ?? 0)]);
        Session::flash('success', 'Message supprimé.');
        return Response::redirect('/admin/messages');
    }
}
