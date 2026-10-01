<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';
requireRole('seller');

$access = coletaAccess((int) (is_string($_GET['coleta'] ?? null) ? $_GET['coleta'] : 0));
if ($access === null || ($access['mode'] === 'coletor' && $access['coleta']['submission_id'] === null)) {
    header('Location: ' . homeUrl());
    exit;
}
$coleta = $access['coleta'];
$isColetor = $access['mode'] === 'coletor';
$jaEnviado = $coleta['submission_id'] !== null;
$saved = $jaEnviado ? (json_decode((string) $coleta['data_json'], true) ?: []) : [];
$type = ($saved['type_pessoa'] ?? $coleta['type_pessoa']) === 'pf' ? 'pf' : 'pj';
if (!$jaEnviado) {
    $doc = formatDoc((string) $coleta['documento']);
    $saved = $coleta['type_pessoa'] === 'pf'
        ? ['responsavel' => ['cpf' => $doc, 'nomeCompleto' => $coleta['nome']]]
        : ['empresa' => ['cnpj' => $doc, 'razaoSocial' => $coleta['nome']]];
}
$prefill = ['type' => $type, 'sub' => $coleta['submission_id'], 'data' => $saved];
$backUrl = $isColetor ? 'coletor.php#pedidos' : 'emissor.php';
$coletorNome = (string) $coleta['coletor_nome'];
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title><?= t('Cadastro de Cliente') ?></title>
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>

<section class="view" id="viewSeller">
  <div class="app-shell">
    <aside class="rail no-print">
      <div class="rail-brand">
        <span class="rail-mark">CC</span>
        <div><strong><?= t('Cadastro') ?></strong><span><?= t('de Cliente') ?></span></div>
      </div>
      <div class="rail-progress">
        <div class="rail-progress-bar"><div id="progressFill" class="rail-progress-fill"></div></div>
        <span id="progressLabel" class="rail-progress-label"><?= t('{pct}% preenchido', ['pct' => 0]) ?></span>
      </div>
      <nav class="rail-nav" aria-label="<?= esc(t('Seções do formulário')) ?>">
        <a href="#sec-tipo"><span class="idx">00</span><?= t('Tipo de Cadastro') ?></a>
        <a href="#sec-empresa" data-pessoa="pj"><span class="idx">01</span><?= t('Dados da Empresa') ?></a>
        <a href="#sec-contato" data-pessoa="pj"><span class="idx">02</span><?= t('Contato da Empresa') ?></a>
        <a href="#sec-endereco"><span class="idx">03</span><?= t('Endereço') ?></a>
        <a href="#sec-evid-empresa" data-pessoa="pj"><span class="idx">04</span><?= t('Evidências da Empresa') ?></a>
        <a href="#sec-responsavel"><span class="idx">05</span><span data-pj="<?= esc(t('Responsável Principal')) ?>" data-pf="<?= esc(t('Seus Dados')) ?>"><?= t('Responsável Principal') ?></span></a>
        <a href="#sec-evid-responsavel"><span class="idx">06</span><span data-pj="<?= esc(t('Evidências do Responsável')) ?>" data-pf="<?= esc(t('Seus Documentos')) ?>"><?= t('Evidências do Responsável') ?></span></a>
      </nav>
      <div class="rail-foot">
        <a class="button" href="<?= esc($backUrl) ?>">← <?= t('Voltar') ?></a>
        <a class="button" href="logout.php"><?= t('Sair') ?></a>
      </div>
    </aside>

    <div class="app-main">
      <div class="app-main-inner">
        <div id="sellerFormWrap">
          <div class="form-head">
            <p class="eyebrow"><?= $isColetor ? t('Cadastro recebido') : t('Pedido de {nome}', ['nome' => esc($coletorNome)]) ?></p>
            <h1><?= $isColetor ? esc($coleta['nome']) : ($jaEnviado ? t('Seu cadastro enviado') : t('Preencha seu cadastro')) ?></h1>
            <p class="muted"><?php if ($isColetor): ?><?= t('Você pode corrigir os dados e salvar. A alteração fica registrada no histórico.') ?><?php elseif ($jaEnviado): ?><?= t('Este é o cadastro que você enviou. Se precisar, corrija e reenvie.') ?><?php else: ?><?= t('Os dados que {nome} informou já vêm preenchidos. Confira e complete o restante.', ['nome' => esc($coletorNome)]) ?><?php endif; ?></p>
          </div>

          <div class="card type-card" id="sec-tipo">
            <div>
              <h2><?= t('Tipo de cadastro') ?></h2>
              <p class="muted"><?= $isColetor ? t('Tipo deste cadastro.') : t('Já vem com o que {nome} escolheu. Troque se precisar.', ['nome' => esc($coletorNome)]) ?></p>
            </div>
            <div class="seg" id="typeSeg" role="radiogroup" aria-label="<?= esc(t('Tipo de cadastro')) ?>" data-value="pj">
              <span class="seg-thumb"></span>
              <button type="button" class="seg-btn is-active" role="radio" aria-checked="true" data-value="pj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 10h5a1 1 0 0 1 1 1v10"/><path d="M8 8h2M8 12h2M8 16h2M3 21h18"/></svg> <?= t('Pessoa Jurídica') ?></button>
              <button type="button" class="seg-btn" role="radio" aria-checked="false" data-value="pf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg> <?= t('Pessoa Física') ?></button>
            </div>
          </div>

          <form id="sellerForm" method="post" action="cadastro_api.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="type_pessoa" id="typePessoa" value="pj">
            <input type="hidden" name="coleta_id" value="<?= (int) $coleta['id'] ?>">

            <div class="card" id="sec-empresa" data-pessoa="pj">
              <div class="section-head"><span class="section-index">01</span><h2><?= t('Dados da Empresa') ?></h2></div>
              <div class="form-grid">
                <label class="field"><span class="field-label"><?= t('CNPJ') ?></span>
                  <input id="cnpjInput" class="mono" name="empresa[cnpj]" placeholder="<?= esc(t('00.000.000/0000-00')) ?>" inputmode="numeric" required></label>
                <label class="field"><span class="field-label"><?= t('Razão social') ?></span>
                  <input name="empresa[razaoSocial]" placeholder="<?= esc(t('Razão social')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Nome fantasia') ?></span>
                  <input name="empresa[nomeFantasia]" placeholder="<?= esc(t('Nome fantasia')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Data de abertura') ?></span>
                  <input type="date" class="mono" name="empresa[dataAbertura]" required></label>
                <label class="field"><span class="field-label"><?= t('Natureza jurídica') ?></span>
                  <input name="empresa[naturezaJuridica]" placeholder="<?= esc(t('LTDA, SA, MEI...')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Faturamento anual (centavos)') ?></span>
                  <input type="number" class="mono" min="0" step="1" name="empresa[faturamentoAnual]" placeholder="<?= esc(t('25000000')) ?>" required>
                  <small class="hint"><?= t('Ex.: R$ 250.000,00 = 25000000') ?></small></label>
                <label class="field"><span class="field-label"><?= t('CNAE') ?></span>
                  <input class="mono" name="empresa[cnae]" placeholder="<?= esc(t('6201501')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Inscrição estadual') ?></span>
                  <input name="empresa[inscricaoEstadual]" placeholder="<?= esc(t('Opcional')) ?>"></label>
              </div>
            </div>

            <div class="card" id="sec-contato" data-pessoa="pj">
              <div class="section-head"><span class="section-index">02</span><h2><?= t('Contato da Empresa') ?></h2></div>
              <div class="form-grid">
                <label class="field"><span class="field-label"><?= t('E-mail') ?></span>
                  <input type="email" name="contatoEmpresa[email]" placeholder="<?= esc(t('financeiro@empresa.com')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('DDI') ?></span>
                  <input id="ddiEmpresa" class="mono" name="contatoEmpresa[ddi]" value="55" required></label>
                <label class="field"><span class="field-label"><?= t('Telefone') ?></span>
                  <input id="telEmpresa" class="mono" name="contatoEmpresa[telefone]" placeholder="<?= esc(t('(11) 3333-4444')) ?>" inputmode="numeric" required></label>
              </div>
            </div>

            <div class="card" id="sec-endereco">
              <div class="section-head"><span class="section-index">03</span><h2><?= t('Endereço') ?></h2></div>
              <div class="form-grid">
                <label class="field"><span class="field-label"><?= t('CEP') ?></span>
                  <input id="cepInput" class="mono" name="endereco[cep]" placeholder="<?= esc(t('00000-000')) ?>" inputmode="numeric" required></label>
                <label class="field"><span class="field-label"><?= t('Logradouro') ?></span>
                  <input name="endereco[logradouro]" placeholder="<?= esc(t('Rua, avenida ou alameda')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Número') ?></span>
                  <input class="mono" name="endereco[numero]" placeholder="<?= esc(t('123')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Bairro') ?></span>
                  <input name="endereco[bairro]" placeholder="<?= esc(t('Centro')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Cidade') ?></span>
                  <input name="endereco[cidade]" placeholder="<?= esc(t('São Paulo')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Estado') ?></span>
                  <input id="estadoInput" class="mono" name="endereco[estado]" placeholder="<?= esc(t('SP')) ?>" maxlength="2" required></label>
                <label class="field"><span class="field-label"><?= t('País de residência') ?></span>
                  <input id="paisInput" class="mono" name="endereco[pais]" value="BR" maxlength="2" required></label>
                <label class="field"><span class="field-label"><?= t('Complemento') ?></span>
                  <input name="endereco[complemento]" placeholder="<?= esc(t('Sala, conjunto, bloco...')) ?>"></label>
              </div>
            </div>

            <div class="card" id="sec-evid-empresa" data-pessoa="pj">
              <div class="section-head"><span class="section-index">04</span><h2><?= t('Evidências da Empresa') ?></h2></div>
              <div class="form-grid">
                <label class="field"><span class="field-label"><?= t('Comprovante de endereço da empresa') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasEmpresa[comprovanteEndereco]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Use o comprovante mais recente disponível.') ?></small></label>
                <label class="field"><span class="field-label"><?= t('Documento principal da empresa') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasEmpresa[documentoPrincipal]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Cartão CNPJ, ficha cadastral ou documento equivalente.') ?></small></label>
                <label class="field"><span class="field-label"><?= t('Contrato social ou ato constitutivo') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasEmpresa[contratoSocial]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Envie a última versão consolidada do documento societário.') ?></small></label>
              </div>
            </div>

            <div class="card" id="sec-responsavel">
              <div class="section-head"><span class="section-index">05</span><h2 data-pj="<?= esc(t('Dados do Responsável Principal')) ?>" data-pf="<?= esc(t('Seus dados pessoais')) ?>"><?= t('Dados do Responsável Principal') ?></h2></div>
              <div class="form-grid">
                <label class="field"><span class="field-label"><?= t('CPF') ?></span>
                  <input id="cpfInput" class="mono" name="responsavel[cpf]" placeholder="<?= esc(t('000.000.000-00')) ?>" inputmode="numeric" required></label>
                <label class="field"><span class="field-label"><?= t('Nome completo') ?></span>
                  <input name="responsavel[nomeCompleto]" placeholder="<?= esc(t('Nome do representante')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Nome da mãe') ?></span>
                  <input name="responsavel[nomeMae]" placeholder="<?= esc(t('Nome da mãe')) ?>"></label>
                <label class="field"><span class="field-label"><?= t('Data de nascimento') ?></span>
                  <input type="date" class="mono" name="responsavel[dataNascimento]" required></label>
                <label class="field"><span class="field-label"><?= t('Sexo') ?></span>
                  <select name="responsavel[sexo]">
                    <option value=""><?= t('Não informado') ?></option>
                    <option value="Masculino"><?= t('Masculino') ?></option>
                    <option value="Feminino"><?= t('Feminino') ?></option>
                  </select></label>
                <label class="field"><span class="field-label"><?= t('Estado civil') ?></span>
                  <select name="responsavel[estadoCivil]">
                    <option value=""><?= t('Não informado') ?></option>
                    <option value="Solteiro(a)"><?= t('Solteiro(a)') ?></option>
                    <option value="Casado(a)"><?= t('Casado(a)') ?></option>
                    <option value="Divorciado(a)"><?= t('Divorciado(a)') ?></option>
                    <option value="Viúvo(a)"><?= t('Viúvo(a)') ?></option>
                    <option value="União estável"><?= t('União estável') ?></option>
                  </select></label>
                <label class="field"><span class="field-label"><?= t('E-mail') ?></span>
                  <input type="email" name="responsavel[email]" placeholder="<?= esc(t('representante@empresa.com')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('DDI') ?></span>
                  <input id="ddiResp" class="mono" name="responsavel[ddi]" value="55" required></label>
                <label class="field"><span class="field-label"><?= t('Telefone') ?></span>
                  <input id="telResp" class="mono" name="responsavel[telefone]" placeholder="<?= esc(t('(11) 99999-9999')) ?>" inputmode="numeric" required></label>
                <label class="field"><span class="field-label"><?= t('Tipo de documento') ?></span>
                  <select name="responsavel[tipoDocumento]">
                    <option value=""><?= t('Não informado') ?></option>
                    <option value="RG"><?= t('RG') ?></option>
                    <option value="CNH"><?= t('CNH') ?></option>
                    <option value="Passaporte"><?= t('Passaporte') ?></option>
                    <option value="CTPS"><?= t('CTPS') ?></option>
                    <option value="Outro"><?= t('Outro') ?></option>
                  </select></label>
                <label class="field"><span class="field-label"><?= t('Número do documento') ?></span>
                  <input class="mono" name="responsavel[numeroDocumento]" placeholder="<?= esc(t('Número do documento')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('Órgão emissor') ?></span>
                  <input name="responsavel[orgaoEmissor]" placeholder="<?= esc(t('SSP')) ?>" required></label>
                <label class="field"><span class="field-label"><?= t('UF emissora') ?></span>
                  <input id="ufEmissoraInput" class="mono" name="responsavel[ufEmissora]" placeholder="<?= esc(t('SP')) ?>" maxlength="2" required></label>
                <label class="field"><span class="field-label"><?= t('Data de emissão') ?></span>
                  <input type="date" class="mono" name="responsavel[dataEmissao]" required></label>
                <label class="field" data-pessoa="pj"><span class="field-label"><?= t('Cargo na empresa') ?></span>
                  <select name="responsavel[cargo]">
                    <option value="Sócio"><?= t('Sócio') ?></option>
                    <option value="Administrador"><?= t('Administrador') ?></option>
                    <option value="Procurador"><?= t('Procurador') ?></option>
                    <option value="Representante legal"><?= t('Representante legal') ?></option>
                    <option value="Outro"><?= t('Outro') ?></option>
                  </select></label>
                <label class="field"><span class="field-label"><?= t('Renda mensal (centavos)') ?></span>
                  <input type="number" class="mono" min="0" step="1" name="responsavel[rendaMensal]" placeholder="<?= esc(t('800000')) ?>" required>
                  <small class="hint"><?= t('Ex.: R$ 8.000,00 = 800000') ?></small></label>
                <label class="field"><span class="field-label"><?= t('Pessoa exposta politicamente (PEP)') ?></span>
                  <select name="responsavel[pep]">
                    <option value=""><?= t('Não informado') ?></option>
                    <option value="Sim"><?= t('Sim') ?></option>
                    <option value="Não"><?= t('Não') ?></option>
                  </select></label>
              </div>
            </div>

            <div class="card" id="sec-evid-responsavel">
              <div class="section-head"><span class="section-index">06</span><h2 data-pj="<?= esc(t('Evidências do Responsável')) ?>" data-pf="<?= esc(t('Seus documentos')) ?>"><?= t('Evidências do Responsável') ?></h2></div>
              <div class="form-grid">
                <label class="field"><span class="field-label"><?= t('Selfie de validação') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasResponsavel[selfie]" accept="image/*">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Use uma imagem recente do responsável.') ?></small></label>
                <label class="field"><span class="field-label"><?= t('Documento oficial (frente)') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasResponsavel[docFrente]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('RG, CNH ou passaporte do responsável.') ?></small></label>
                <label class="field"><span class="field-label"><?= t('Documento oficial (verso)') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasResponsavel[docVerso]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Envie quando houver informações relevantes no verso.') ?></small></label>
                <label class="field"><span class="field-label"><?= t('Comprovante de residência') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasResponsavel[comprovanteResidencia]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Comprovante recente do responsável principal.') ?></small></label>
                <label class="field" data-pessoa="pj"><span class="field-label"><?= t('Documento de procuração') ?></span>
                  <span class="file-input">
                    <span class="file-icon">◧</span>
                    <input type="file" name="evidenciasResponsavel[procuracao]" accept="image/*,.pdf">
                    <span class="file-name"><?= t('Nenhum arquivo escolhido') ?></span>
                  </span>
                  <small class="hint"><?= t('Anexe somente quando a assinatura ocorrer por procurador.') ?></small></label>
              </div>
            </div>

            <button type="submit" class="primary"><?= $isColetor ? t('Salvar alterações') : ($jaEnviado ? t('Reenviar cadastro') : t('Enviar cadastro')) ?></button>
            <p class="error" id="submitError" hidden></p>
          </form>
        </div>

        <div id="sellerThanks" class="thanks-wrap" hidden>
          <div class="card thanks-card">
            <svg class="thanks-check" viewBox="0 0 48 48" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="24" r="21"/><path d="M15 24l6 6 12-13"/></svg>
            <h1><?= $isColetor ? t('Alterações salvas') : t('Cadastro enviado') ?></h1>
            <p><?= $isColetor ? t('O cadastro foi atualizado e a alteração ficou registrada no histórico.') : t('Seus dados foram enviados para {nome}. Uma cópia fica em "Meus pedidos" caso precise reenviar.', ['nome' => esc($coletorNome)]) ?></p>
            <div class="actions">
              <a class="button primary" href="<?= esc($backUrl) ?>"><?= $isColetor ? t('Voltar aos pedidos') : t('Ver meus pedidos') ?></a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?= jsI18n() ?>
<script src="<?= av('assets/mask.js') ?>"></script>
<script src="<?= av('assets/cliente.js') ?>"></script>
<script src="<?= av('assets/cep.js') ?>"></script>
<script>
(function () {
  var P = <?= json_encode($prefill, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  window.CADASTRO_TYPE = P.type;
  var form = document.getElementById('sellerForm');
  Object.keys(P.data || {}).forEach(function (group) {
    var g = P.data[group];
    if (!g || typeof g !== 'object') return;
    Object.keys(g).forEach(function (field) {
      var v = g[field];
      var el = form.querySelector('[name="' + group + '[' + field + ']"]');
      if (!el) return;
      if (el.type === 'file') {
        if (v && v.originalName && P.sub) {
          var wrap = el.closest('.file-input');
          var span = wrap.querySelector('.file-name');
          var link = document.createElement('a');
          link.href = 'download.php?id=' + encodeURIComponent(P.sub) + '&group=' + encodeURIComponent(group) + '&field=' + encodeURIComponent(field);
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = v.originalName;
          wrap.classList.add('has-file');
          span.textContent = t('Já enviado:') + ' ';
          span.appendChild(link);
          span.appendChild(document.createTextNode(' (' + t('envie outro só para trocar') + ')'));
        }
        return;
      }
      if (typeof v === 'string' && v !== '') el.value = v;
    });
  });
})();
</script>
<script>
(function(){
  var seg = document.getElementById('typeSeg');
  var hidden = document.getElementById('typePessoa');
  var form = document.getElementById('sellerForm');
  if(!seg || !hidden || !form) return;

  function apply(type){
    seg.setAttribute('data-value', type);
    hidden.value = type;
    seg.querySelectorAll('.seg-btn').forEach(function(b){
      var on = b.getAttribute('data-value') === type;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    document.querySelectorAll('[data-pessoa]').forEach(function(el){
      var show = el.getAttribute('data-pessoa').indexOf(type) !== -1;
      el.hidden = !show;
      el.querySelectorAll('input, select, textarea').forEach(function(inp){
        if(inp.dataset.wasRequired === undefined) inp.dataset.wasRequired = inp.required ? '1' : '0';
        inp.disabled = !show;
        inp.required = show && inp.dataset.wasRequired === '1';
      });
    });
    document.querySelectorAll('[data-pj][data-pf]').forEach(function(el){
      el.textContent = el.getAttribute('data-' + type);
    });
    var n = 0;
    document.querySelectorAll('.rail-nav a').forEach(function(a){
      if(a.hidden) return;
      var idx = a.querySelector('.idx');
      if(idx) idx.textContent = String(n++).padStart(2, '0');
    });
    n = 1;
    document.querySelectorAll('#sellerForm .card .section-index').forEach(function(s){
      var card = s.closest('.card');
      if(card && card.hidden) return;
      s.textContent = String(n++).padStart(2, '0');
    });
    form.dispatchEvent(new Event('change', {bubbles:true}));
  }

  seg.addEventListener('click', function(e){
    var b = e.target.closest('.seg-btn');
    if(b) apply(b.getAttribute('data-value'));
  });
  seg.addEventListener('keydown', function(e){
    if(e.key === 'ArrowLeft' || e.key === 'ArrowRight'){
      apply(hidden.value === 'pj' ? 'pf' : 'pj');
      seg.querySelector('.seg-btn.is-active').focus();
    }
  });
  apply(window.CADASTRO_TYPE === 'pf' ? 'pf' : 'pj');
})();
</script>
<script src="<?= av('assets/heartbeat.js') ?>"></script>
</body>
</html>
