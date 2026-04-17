<?php
declare(strict_types=1);
namespace App\Modules\Actualites;

use App\Core\{Container, Paginator, Request, Response, Session, View};

final class AdminController
{
    private const PER_PAGE = 20;

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
}
