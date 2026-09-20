<?php
require_once '../include/auth.php';
require_once '../include/conexao.php';

if (!isset($_SESSION['nivel']) || !in_array($_SESSION['nivel'], ['gerente', 'admin'])) {
    header("Location: ../fiscal.php?erro=sem_permissao");
    exit;
}

if (!class_exists('ZipArchive')) {
    exit('A extensão PHP "zip" não está habilitada neste servidor. Peça ao administrador do servidor para ativar ext-zip.');
}

$stmt = $mysql->prepare("SELECT numero_nota, xml_path FROM notas_fiscais WHERE xml_path IS NOT NULL AND xml_path != '' AND MONTH(data_emissao) = MONTH(CURDATE()) AND YEAR(data_emissao) = YEAR(CURDATE())");
$stmt->execute();
$notas = $stmt->get_result();

$tmpZip = tempnam(sys_get_temp_dir(), 'nf_xml_');
$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::OVERWRITE);

$adicionados = 0;
while ($nf = $notas->fetch_assoc()) {
    $caminhoArquivo = realpath(__DIR__ . '/../' . ltrim($nf['xml_path'], '/'));
    // Garante que o arquivo resolvido continua dentro da pasta do projeto (evita path traversal)
    if ($caminhoArquivo && str_starts_with($caminhoArquivo, realpath(__DIR__ . '/..')) && is_file($caminhoArquivo)) {
        $nomeNoZip = 'NF-' . $nf['numero_nota'] . '.xml';
        $zip->addFile($caminhoArquivo, $nomeNoZip);
        $adicionados++;
    }
}
$zip->close();

if ($adicionados === 0) {
    unlink($tmpZip);
    exit('Nenhum arquivo XML encontrado para o mês atual.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="notas_fiscais_' . date('Y-m') . '.zip"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
exit;
