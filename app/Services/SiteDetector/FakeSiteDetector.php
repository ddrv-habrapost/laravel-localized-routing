<?php

declare(strict_types=1);

namespace App\Services\SiteDetector;

use App\Contracts\SiteDetector;
use App\Entities\Site;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Для демонстрации сайты находятся в памяти.
 * В реальном проекте всё хранится в БД, что позволяет изменять настройки через админку.
 */
class FakeSiteDetector implements SiteDetector
{

    /**
     * @var Site[] Хранилище
     */
    private $sites;

    public function __construct()
    {
        $sites = [
            'localhost' => [ // Все языки
                'default' => 'en',
                'support' => ['ru', 'de', 'fr'],
            ],
            'site-all.local' => [ // Все языки
                'default' => 'en',
                'support' => ['ru', 'de', 'fr'],
            ],
            'site-ru.local' => [ // Только русский
                'default' => 'ru',
                'support' => [],
            ],
            'site-en.local' => [
                'default' => 'en', // Только английский
                'support' => [],
            ],
            'site-de.local' => [
                'default' => 'de', // Только немецкий
                'support' => [],
            ],
            'site-fr.local' => [
                'default' => 'fr', // Только французский
                'support' => [],
            ],
            'site-eur.local' => [ // Немецкий и французский
                'default' => 'de',
                'support' => ['fr'],
            ],
        ];
        foreach ($sites as $domain => $site) {
            $default = $site['default'];
            $support = array_merge([$default], $site['support']);
            $this->sites[$domain] = new Site($domain, $default, $support);
        }
    }

    public function detect(string $host): Site
    {
        $host = trim(mb_strtolower($host));
        if (!array_key_exists($host, $this->sites)) {
            throw new NotFoundHttpException();
        }
        return $this->sites[$host];
    }
}
