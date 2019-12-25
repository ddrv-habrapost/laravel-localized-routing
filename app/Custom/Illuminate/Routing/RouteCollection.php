<?php

namespace App\Custom\Illuminate\Routing;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection as BaseRouteCollection;
use Serializable;

class RouteCollection extends BaseRouteCollection implements Serializable
{

    /**
     * @var array Конфигурация локализации роутов.
     */
    private $config;

    private $localized = [];

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Заменяет маски локлизуемых роутов.
     *
     * @param string $language Язык
     */
    public function localize(string $language)
    {
        $this->flushLocalizedRoutes();
        foreach ($this->config as $name => $placeholders) {
            if (!$this->hasNamedRoute($name) || empty($placeholders)) {
                continue;
            }

            /*
             * Получаем именованный роут
             */
            $route = $this->getByName($name);

            /*
             * Запоминаем
             */
            $this->localized[$name] = $route;

            /*
             * Удаляем его из коллекции
             */
            $this->removeRoute($route);

            /*
             * Меняем шаблон
             */
            $new = clone $route;
            $uri = $new->uri();
            foreach ($placeholders as $placeholder => $paths) {
                if (!array_key_exists($language, $paths)) {
                    continue;
                }
                $value = $paths[$language];
                $uri = str_replace('--' . $placeholder . '--', $value, $uri);
            }
            $new->setUri($uri);
            $this->add($new);
        }

        /*
         * Обновляем индексы
         */
        $this->refreshNameLookups();
        $this->refreshActionLookups();
    }

    private function removeRoute(Route $route)
    {
        $uri = $route->uri();
        $domainAndUri = $route->getDomain().$uri;
        foreach ($route->methods() as $method) {
            $key = $method.$domainAndUri;
            if (array_key_exists($key, $this->allRoutes)) {
                unset($this->allRoutes[$key]);
            }
            if (array_key_exists($uri, $this->routes[$method])) {
                unset($this->routes[$method][$uri]);
            }
        }
    }

    private function flushLocalizedRoutes()
    {
        foreach ($this->localized as $name => $route) {
            /*
             * Получаем именованный роут
             */
            $old = $this->getByName($name);

            /*
             * Удаляем его из коллекции
             */
            $this->removeRoute($old);

            /*
             * Добавляем исходный
             */
            $this->add($route);
        }
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize([
            'routes' => $this->routes,
            'allRoutes' => $this->allRoutes,
            'nameList' => $this->nameList,
            'actionList' => $this->actionList,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->routes = $data['routes'];
        $this->allRoutes = $data['allRoutes'];
        $this->nameList = $data['nameList'];
        $this->actionList = $data['actionList'];
    }
}
