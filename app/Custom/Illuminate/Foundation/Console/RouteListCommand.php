<?php

declare(strict_types=1);

namespace App\Custom\Illuminate\Foundation\Console;

use Illuminate\Foundation\Console\RouteListCommand as BaseCommand;
use Symfony\Component\Console\Input\InputOption;

class RouteListCommand extends BaseCommand
{

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $locales = $this->option('locale');

        /*
         * Выполняем родительскую команду для каждой локали
         */
        foreach ($locales as $locale) {
            if ($locale && in_array($locale, config('app.supported_locales'))) {
                $this->output->title($locale);
                $this->laravel->setLocale($locale);
                $this->router = $this->laravel->get('router');
                parent::handle();
            }
        }
    }

    protected function getOptions()
    {
        /*
         * Все поддерживаемые приложением локали
         */
        $all = config('app.supported_locales');

        /*
         * Определяем опции родительской команды
         */
        $result = parent::getOptions();

        /*
         * Добавляем опцию локалей
         */
        $result[] = ['locale', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Locales', $all];
        return $result;
    }
}
