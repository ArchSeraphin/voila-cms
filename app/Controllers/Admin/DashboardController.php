<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Auth, Container, DB, ModuleRegistry, Request, Response, View};

final class DashboardController
{
    public function index(Request $req): Response
    {
        /** @var View $view */
        $view = Container::get(View::class);
        $auth = new Auth(DB::conn());

        $messagesUnread = (int)DB::conn()->query(
            "SELECT COUNT(*) FROM contact_messages WHERE read_at IS NULL"
        )->fetchColumn();

        /** @var ModuleRegistry $reg */
        $reg = Container::get(ModuleRegistry::class);
        $active = $reg->active();

        $actualitesCount = 0;
        if ($reg->has('actualites')) {
            $actualitesCount = (int)DB::conn()->query("SELECT COUNT(*) FROM actualites WHERE published=1")->fetchColumn();
        }

        return new Response($view->render('admin/dashboard.html.twig', [
            'user'              => $auth->user(),
            'messages_unread'   => $messagesUnread,
            'modules_count'     => count($active),
            'actualites_count'  => $actualitesCount,
            'has_actualites'    => $reg->has('actualites'),
        ]));
    }
}
