<?php
declare(strict_types=1);

const SUPPORTED_LANGS = ['pt_BR', 'en', 'es'];
const DEFAULT_LANG = 'pt_BR';
const LANG_NAMES = ['pt_BR' => ['🇧🇷', 'Português'], 'en' => ['🇬🇧', 'English'], 'es' => ['🇪🇸', 'Español']];

function getCurrentLang(): string
{
    $lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? DEFAULT_LANG);
    return is_string($lang) && in_array($lang, SUPPORTED_LANGS, true) ? $lang : DEFAULT_LANG;
}

function setLang(string $lang): void
{
    if (!in_array($lang, SUPPORTED_LANGS, true)) {
        $lang = DEFAULT_LANG;
    }
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}

/** Dicionario do idioma atual: chave = texto em portugues. */
function langDict(): array
{
    static $cache = [];
    $lang = getCurrentLang();
    if ($lang === DEFAULT_LANG) {
        return [];
    }
    if (!isset($cache[$lang])) {
        $file = __DIR__ . '/lang/' . $lang . '.json';
        $cache[$lang] = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    return $cache[$lang];
}

/** Traduz um texto em portugues para o idioma atual; {chave} recebe os parametros. */
function t(string $pt, array $params = []): string
{
    $s = langDict()[$pt] ?? $pt;
    foreach ($params as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

function htmlLang(): string
{
    return ['pt_BR' => 'pt-BR', 'en' => 'en', 'es' => 'es'][getCurrentLang()];
}

/** Disponibiliza o dicionario e a funcao t() para o JavaScript da pagina. */
function jsI18n(): string
{
    return '<script>window.I18N=' . json_encode(langDict() ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
        . ';function t(s,p){s=(window.I18N&&window.I18N[s])||s;if(p){Object.keys(p).forEach(function(k){s=s.split("{"+k+"}").join(p[k]);});}return s;}</script>';
}
