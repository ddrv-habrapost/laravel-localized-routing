<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Entities\Site;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface SiteDetector
{

    /**
     * Определяет сайт по хосту
     *
     * @param string $host Хост
     *
     * @return Site Сущность сайта
     *
     * @throws NotFoundHttpException Если сайт не известен
     */
    public function detect(string $host): Site;
}
