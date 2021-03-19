<?php

namespace Grav\Plugin\TranslateDate\Twig;

use DateTime;
use Grav\Common\Config\Config;
use \Grav\Common\Grav;
use Grav\Common\Language\Language;

class FilterTd
{
    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Language
     */
    protected $language;

    /**
     * SwiperJSExtension constructor
     */
    public function __construct()
    {
        $this->grav     = Grav::instance();
        $this->config   = $this->grav->get('config');
        $this->language = $this->grav->get('language');
    }

    /**
     * @param             $date
     * @param string|null $language
     * @param string|null $format
     *
     * @return string
     */
    public function translateDate($date, ?string $language = null, ?string $format = null)
    {
        try {
            if (is_int($date)) {
                $date = new DateTime("@$date");
                $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } elseif (is_scalar($date)) {
                $date = new DateTime($date);
            }
        } catch (\Exception $e) {
            return $date;
        }

        if (!$date instanceof DateTime) {
            return $date;
        }

        $language = $language ?? $this->getLanguage();
        $format   = $format ?: $this->getFormat($language);

        if ($this->config->get('plugins.translate-date.processor') === 'intl') {
            return $this->translateDateIntl($date, $language, $format);
        }

        return $this->translateDateBasic($date, $language, $format);
    }

    /**
     * @return string
     */
    protected function getLanguage(): string
    {
        $language = $this->language->getLanguage() ?: null;

        if ($language) {
            return $language;
        }

        if ($this->config->get('plugins.translate-date.processor') === 'intl') {
            return \Locale::getDefault();
        }

        return 'en';
    }

    /**
     * @param string $language
     *
     * @return string
     */
    protected function getFormat(string $language): string
    {
        $formats = $this->config->get('plugins.translate-date.formats', []);

        if (!empty($formats[$language])) {
            return (string)$formats[$language];
        }

        if ($this->config->get('plugins.translate-date.processor') === 'intl') {
            $formatter = \IntlDateFormatter::create(
                $language,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE
            );

            return $formatter->getPattern();
        }

        return 'Y-m-d H:i';
    }

    /**
     * @param DateTime $date
     * @param string   $locale
     * @param string   $format
     *
     * @return string
     */
    protected function translateDateIntl(DateTime $date, string $locale, string $format): string
    {
        try {
            $formatter = \IntlDateFormatter::create(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                \IntlTimeZone::createTimeZone($date->getTimezone()->getName()),
                \IntlDateFormatter::TRADITIONAL,
                $format
            );

            return $formatter->format($date->getTimestamp());
        } catch (\Exception $exception) {
            $language_parts = explode('_', $locale);

            return $date->format(
                $this->getFormat(reset($language_parts))
            );
        }
    }

    /**
     * @param DateTime    $date
     * @param string|null $language
     * @param string|null $format
     *
     * @return string
     */
    protected function translateDateBasic(DateTime $date, string $language, string $format): string
    {
        $languages    = [$language];
        $replacements = $this->getReplacements($date, $format, $languages);

        if (empty($replacements)) {
            return $date->format($format);
        }

        foreach ($replacements as $index => $data) {
            if (empty($data['translation'])) {
                continue;
            }

            $pos = strpos($format, $data['char']);

            if ($pos === false) {
                continue;
            }

            $format = (string)substr_replace($format, $index, $pos, strlen($data['char']));
        }

        return str_replace(
            array_keys($replacements),
            array_column($replacements, 'translation'),
            $date->format($format)
        );
    }

    /**
     * @param DateTime $date
     * @param string   $format
     * @param array    $languages
     *
     * @return array
     */
    protected function getReplacements(DateTime $date, string $format, array $languages): array
    {
        $replacements = [];

        $alpha = str_split(
            preg_replace('/[^a-zA-Z]+/', '', $format)
        );

        foreach ($alpha as $index => $char) {
            $translation = $this->getTranslation(
                sprintf('PLUGIN_TRANSLATE_DATE.%s', $char),
                $languages
            );

            if (!$translation) {
                if ($char === 'F') {
                    $translation = $this->getTranslation('GRAV.MONTHS_OF_THE_YEAR', $languages);
                } elseif ($char === 'l') {
                    $translation = $this->getTranslation('GRAV.DAYS_OF_THE_WEEK', $languages);
                }
            }

            if ($translation) {
                $replacements[sprintf('%%%d', $index)] = [
                    'char'        => $char,
                    'translation' => $this->getTranslationValue($date, $char, $translation),
                ];
            }
        }

        return $replacements;
    }

    /**
     * @param string     $string
     * @param array|null $languages
     *
     * @return string[]|null
     */
    protected function getTranslation(string $string, ?array $languages): ?array
    {
        $translation = $this->language->translate($string, $languages, true);

        if ($translation === $string) {
            return null;
        }

        return $translation;
    }

    /**
     * @param DateTime $date
     * @param string   $char
     * @param array    $translation
     *
     * @return string|null
     */
    protected function getTranslationValue(DateTime $date, string $char, array $translation): ?string
    {
        $value = null;

        switch ($char) {
            case 'l':
            case 'D':
                $value = $translation[$date->format('N') - 1] ?? null;
                break;

            case 'F':
            case 'M':
                $value = $translation[$date->format('n') - 1] ?? null;
                break;
        }

        return $value;
    }
}
