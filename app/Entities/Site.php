<?php

declare(strict_types=1);

namespace App\Entities;

class Site
{

    /**
     * @var string Домен сайта
     */
    private $domain;

    /**
     * @var string Язык по умолчанию
     */
    private $defaultLanguage;

    /**
     * @var string[] Список поддержиаемых языков
     */
    private $supportedLanguages = [];

    /**
     * @param string   $domain             Домен
     * @param string   $defaultLanguage    Язык по умолчанию
     * @param string[] $supportedLanguages Список поддерживаемых языков
     */
    public function __construct(string $domain, string $defaultLanguage, array $supportedLanguages)
    {
        $this->domain = $domain;
        $this->defaultLanguage = $defaultLanguage;
        if (!in_array($defaultLanguage, $supportedLanguages)) {
            $supportedLanguages[] = $defaultLanguage;
        }
        $this->supportedLanguages = $supportedLanguages;
    }

    /**
     * Возвращает домен сайта
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Возвращает язык по умолчанию для сайта
     *
     * @return string
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * Возвращает список поддерживаемых сайтом языков
     *
     * @return string[]
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Проверяет поддержку сайтом языка
     *
     * @param string $language
     * @return bool
     */
    public function isLanguageSupported(string $language): bool
    {
        return in_array($language, $this->supportedLanguages);
    }

    /**
     * Проверяет, является ли передаваемый язык основным
     *
     * @param string $language
     * @return bool
     */
    public function isLanguageDefault(string $language): bool
    {
        return $language === $this->defaultLanguage;
    }
}
