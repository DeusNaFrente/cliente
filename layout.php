<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

function esc(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Acrescenta a data de modificacao do arquivo na URL, para o navegador buscar a versao nova assim que ela muda. */
function av(string $path): string
{
    $file = __DIR__ . '/' . $path;
    $v = is_file($file) ? filemtime($file) : time();
    return $path . '?v=' . $v;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if (preg_match('/^./u', $p, $m)) {
            $out .= $m[0];
        }
    }
    return mb_strtoupper($out, 'UTF-8');
}

function fmtTs(int $ts): string
{
    return (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
}

function renderFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    echo '<div class="notice notice-warn">' . esc(t((string) $_SESSION['flash'])) . '</div>';
    unset($_SESSION['flash']);
}

/** Botao de idioma; troca e volta para a mesma pagina. */
function renderLangSelector(string $extraClass = ''): void
{
    $lang = getCurrentLang();
    $back = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'login.php')) . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
    ?>
    <div class="lang-selector <?= esc($extraClass) ?>">
      <button type="button" class="lang-button" aria-haspopup="true" aria-expanded="false" title="<?= esc(t('Idioma')) ?>">🌐</button>
      <form method="post" action="lang.php" class="lang-dropdown">
        <input type="hidden" name="back" value="<?= esc($back) ?>">
        <?php foreach (LANG_NAMES as $code => [$flag, $label]): ?>
        <button type="submit" name="lang" value="<?= esc($code) ?>" class="lang-option<?= $lang === $code ? ' active' : '' ?>"><span><?= $flag ?></span><?= esc($label) ?></button>
        <?php endforeach; ?>
      </form>
    </div>
    <script src="<?= av('assets/lang.js') ?>" defer></script>
    <?php
}

/** Alternador de perfil (Coletor/Emissor); admin ganha uma opcao extra para voltar ao painel. */
function renderProfileSwitch(): void
{
    $isAdmin = currentRole() === 'admin';
    $options = $isAdmin ? ['admin' => 'Admin', 'coletor' => 'Coletor', 'emissor' => 'Emissor'] : ['coletor' => 'Coletor', 'emissor' => 'Emissor'];
    $active = currentProfile() ?? ($isAdmin ? 'admin' : null);
    $index = array_search($active, array_keys($options), true);
    $index = $index === false ? 0 : $index;
    ?>
    <form method="post" action="perfil.php" class="profile-switch">
      <span class="profile-switch-label"><?= t('Perfil') ?></span>
      <div class="seg" data-index="<?= $index ?>" style="--seg-n:<?= count($options) ?>">
        <span class="seg-thumb"></span>
        <?php foreach ($options as $value => $label): ?>
        <button type="submit" name="profile" value="<?= esc($value) ?>" class="seg-btn<?= $value === $active ? ' is-active' : '' ?>"><?= t($label) ?></button>
        <?php endforeach; ?>
      </div>
    </form>
    <script>
    document.querySelectorAll('.profile-switch .seg-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        var seg = b.closest('.seg');
        var all = Array.prototype.slice.call(seg.querySelectorAll('.seg-btn'));
        seg.setAttribute('data-index', all.indexOf(b));
        all.forEach(function (x) { x.classList.toggle('is-active', x === b); });
      });
    });
    </script>
    <?php
}

/** Menu lateral das contas comuns, com a troca de perfil coletor/emissor. */
function renderUserRail(array $nav): void
{
    $profile = currentProfile();
    ?>
    <aside class="rail no-print">
      <div class="rail-brand">
        <span class="rail-mark">CC</span>
        <div><strong><?= t('Cadastro') ?></strong><span><?= t('de Cliente') ?></span></div>
      </div>
      <?php renderProfileSwitch(); ?>
      <nav class="rail-nav" aria-label="<?= esc(t('Menu')) ?>">
        <?php foreach ($nav as $item): ?>
        <a href="<?= esc($item['href']) ?>"<?= !empty($item['active']) ? ' class="active"' : '' ?>><span class="idx"><?= esc($item['idx']) ?></span><?= esc(t($item['label'])) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="rail-foot">
        <div class="rail-account">
          <span class="rail-avatar"><?= esc(initials(currentUsername() ?? '')) ?></span>
          <div class="rail-account-info">
            <span class="rail-username" title="<?= esc(currentUsername()) ?>"><?= esc(currentUsername()) ?></span>
            <span class="rail-profile-label"><?= $profile === 'coletor' ? t('Coletor') : ($profile === 'emissor' ? t('Emissor') : t('Perfil')) ?></span>
          </div>
        </div>
        <div class="rail-foot-row">
          <?php renderLangSelector('lang-rail'); ?>
          <a class="button rail-logout" href="logout.php">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            <?= t('Sair') ?>
          </a>
        </div>
      </div>
    </aside>
    <?php
}
