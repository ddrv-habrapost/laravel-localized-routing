<?php

namespace App\Http\Middleware;

use App\Contracts\SiteDetector;
use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ViewData
{

    /**
     * @var ViewFactory
     */
    private $view;

    /**
     * @var SiteDetector
     */
    private $detector;

    public function __construct(ViewFactory $view, SiteDetector $detector)
    {
        $this->view = $view;
        $this->detector = $detector;
    }

    public function handle(Request $request, Closure $next)
    {
        /*
         * Определяем сайт
         */
        $site = $this->detector->detect($request->getHost());

        /*
         * Передаём в шаблон панели выбора языка ссылки
         */
        $languages = [];
        foreach ($site->getSupportedLanguages() as $language) {
            $url = '/';
            if (!$site->isLanguageDefault($language)) {
                $url .= $language;
            }
            $languages[$language] = $url;
        }

        $this->view->composer(['components/languages'], function(View $view) use ($languages) {
            $view->with('languages', $languages);
        });

        return $next($request);
    }
}
