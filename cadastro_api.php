<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $code, string $message, ?string $field = null): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message, 'field' => $field], JSON_UNESCAPED_UNICODE);
    exit;
}

function postValue(string $group, string $field): string
{
    $v = $_POST[$group][$field] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', trim($v));
    return $clean === false ? '' : $clean;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, t('Método não permitido.'));
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    fail(403, t('Requisição inválida.'));
}
touchSession();
$access = coletaAccess((int) (is_string($_POST['coleta_id'] ?? null) ? $_POST['coleta_id'] : 0));
if ($access === null) {
    fail(403, t('Você não tem acesso a este cadastro. Entre novamente.'));
}
$coleta = $access['coleta'];
$mode = $access['mode'];

if (($_POST['delete'] ?? '') === '1') {
    if ($mode !== 'emissor' || $coleta['submission_id'] === null) {
        fail(400, t('Não há cadastro para apagar.'));
    }
    $subId = (string) $coleta['submission_id'];
    $dir = UPLOADS_DIR . '/' . $subId;
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    getDB()->prepare('DELETE FROM submissions WHERE id = ?')->execute([$subId]);
    getDB()->prepare("UPDATE coletas SET status = 'aberto' WHERE id = ?")->execute([(int) $coleta['id']]);
    logColetaEvento((int) $coleta['id'], currentUserId(), 'excluido_emissor');
    echo json_encode(['ok' => true]);
    exit;
}

if ($mode === 'coletor' && $coleta['submission_id'] === null) {
    fail(400, t('Este pedido ainda não recebeu cadastro.'));
}

$type = ($_POST['type_pessoa'] ?? '') === 'pf' ? 'pf' : 'pj';
$fields = [
    'empresa' => ['cnpj', 'razaoSocial', 'nomeFantasia', 'dataAbertura', 'naturezaJuridica', 'faturamentoAnual', 'cnae', 'inscricaoEstadual'],
    'contatoEmpresa' => ['email', 'ddi', 'telefone'],
    'endereco' => ['cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado', 'pais', 'complemento'],
    'responsavel' => ['cpf', 'nomeCompleto', 'nomeMae', 'dataNascimento', 'sexo', 'estadoCivil', 'email', 'ddi', 'telefone',
        'tipoDocumento', 'numeroDocumento', 'orgaoEmissor', 'ufEmissora', 'dataEmissao', 'cargo', 'rendaMensal', 'pep'],
];
$data = ['type_pessoa' => $type];
foreach ($fields as $group => $list) {
    foreach ($list as $field) {
        $data[$group][$field] = $type === 'pf' && ($group === 'empresa' || $group === 'contatoEmpresa' || ($group === 'responsavel' && $field === 'cargo'))
            ? ''
            : postValue($group, $field);
    }
}

$required = [
    ['endereco', 'cep'], ['endereco', 'logradouro'], ['endereco', 'numero'], ['endereco', 'bairro'],
    ['endereco', 'cidade'], ['endereco', 'estado'], ['endereco', 'pais'],
    ['responsavel', 'cpf'], ['responsavel', 'nomeCompleto'], ['responsavel', 'dataNascimento'],
    ['responsavel', 'email'], ['responsavel', 'ddi'], ['responsavel', 'telefone'],
    ['responsavel', 'tipoDocumento'], ['responsavel', 'numeroDocumento'], ['responsavel', 'orgaoEmissor'],
    ['responsavel', 'ufEmissora'], ['responsavel', 'dataEmissao'], ['responsavel', 'rendaMensal'],
];
if ($type === 'pj') {
    $required = array_merge($required, [
        ['empresa', 'cnpj'], ['empresa', 'razaoSocial'], ['empresa', 'nomeFantasia'], ['empresa', 'dataAbertura'],
        ['empresa', 'naturezaJuridica'], ['empresa', 'faturamentoAnual'], ['empresa', 'cnae'],
        ['contatoEmpresa', 'email'], ['contatoEmpresa', 'ddi'], ['contatoEmpresa', 'telefone'], ['responsavel', 'cargo'],
    ]);
}
foreach ($required as [$group, $field]) {
    if ($data[$group][$field] === '') {
        fail(400, t('Preencha todos os campos obrigatórios antes de enviar.'), $group . '[' . $field . ']');
    }
}
if (!validCpf(onlyDigits($data['responsavel']['cpf']))) {
    fail(400, $type === 'pf' ? t('CPF inválido.') : t('CPF do responsável inválido.'), 'responsavel[cpf]');
}
if ($type === 'pj' && !validCnpj(onlyDigits($data['empresa']['cnpj']))) {
    fail(400, t('CNPJ inválido.'), 'empresa[cnpj]');
}
if (!filter_var($data['responsavel']['email'], FILTER_VALIDATE_EMAIL)) {
    fail(400, t('Confira os e-mails informados.'), 'responsavel[email]');
}
if ($type === 'pj' && !filter_var($data['contatoEmpresa']['email'], FILTER_VALIDATE_EMAIL)) {
    fail(400, t('Confira os e-mails informados.'), 'contatoEmpresa[email]');
}

$old = $coleta['data_json'] !== null ? (json_decode((string) $coleta['data_json'], true) ?: []) : [];
$subId = $coleta['submission_id'] ?? (date('Ymd_His') . '_' . bin2hex(random_bytes(4)));
$dir = UPLOADS_DIR . '/' . $subId;
$fileFields = [
    'evidenciasEmpresa' => ['comprovanteEndereco', 'documentoPrincipal', 'contratoSocial'],
    'evidenciasResponsavel' => ['selfie', 'docFrente', 'docVerso', 'comprovanteResidencia', 'procuracao'],
];
foreach ($fileFields as $group => $list) {
    $data[$group] = is_array($old[$group] ?? null) ? $old[$group] : [];
    foreach ($list as $field) {
        $err = $_FILES[$group]['error'][$field] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            fail(400, t('Falha ao enviar um dos arquivos. Tente de novo.'), $group . '[' . $field . ']');
        }
        $size = (int) $_FILES[$group]['size'][$field];
        if ($size > MAX_FILE_SIZE) {
            fail(400, t('Um dos arquivos passa de 8 MB. Reduza o tamanho e tente de novo.'), $group . '[' . $field . ']');
        }
        $name = (string) $_FILES[$group]['name'][$field];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_FILE_EXT, true)) {
            fail(400, t('Tipo de arquivo não permitido: .') . $ext, $group . '[' . $field . ']');
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            fail(500, t('Erro ao preparar o armazenamento no servidor.'));
        }
        if (!empty($data[$group][$field]['storedName'])) {
            $previous = $dir . '/' . basename((string) $data[$group][$field]['storedName']);
            if (is_file($previous)) {
                unlink($previous);
            }
        }
        $stored = $group . '__' . $field . '.' . $ext;
        if (!move_uploaded_file($_FILES[$group]['tmp_name'][$field], $dir . '/' . $stored)) {
            fail(500, t('Erro ao salvar o arquivo no servidor.'));
        }
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($dir . '/' . $stored) : '';
        $data[$group][$field] = [
            'originalName' => mb_substr((string) preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $name), 0, 120),
            'storedName' => $stored,
            'mime' => $mime ?: 'application/octet-stream',
            'size' => $size,
        ];
    }
}
$data['createdAt'] = $old['createdAt'] ?? date('c');
$data['updatedAt'] = date('c');
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fail(500, t('Erro ao processar os dados do cadastro.'));
}

$nome = mb_substr($type === 'pf' ? $data['responsavel']['nomeCompleto'] : $data['empresa']['razaoSocial'], 0, 200);
$doc = onlyDigits($type === 'pf' ? $data['responsavel']['cpf'] : $data['empresa']['cnpj']);
$db = getDB();
$db->beginTransaction();
try {
    if ($coleta['submission_id'] === null) {
        $db->prepare('INSERT INTO submissions (id, user_id, coleta_id, type_pessoa, data_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$subId, (int) $coleta['emissor_id'], (int) $coleta['id'], $type, $json]);
        $evento = 'enviado';
    } else {
        $db->prepare('UPDATE submissions SET type_pessoa = ?, data_json = ? WHERE id = ?')->execute([$type, $json, $subId]);
        $evento = $mode === 'coletor' ? 'editado' : 'reenviado_emissor';
    }
    $db->prepare("UPDATE coletas SET type_pessoa = ?, nome = ?, documento = ?, status = 'recebido' WHERE id = ?")
        ->execute([$type, $nome, $doc, (int) $coleta['id']]);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log('cadastro_api: ' . $e->getMessage());
    fail(500, t('Erro ao salvar o cadastro. Tente de novo.'));
}
logColetaEvento((int) $coleta['id'], currentUserId(), $evento);
if ($evento !== 'editado') {
    sendCadastroRecebidoEmail((int) $coleta['coletor_id'], $nome, $evento === 'enviado');
}

echo json_encode(['ok' => true, 'id' => $subId, 'evento' => $evento]);
