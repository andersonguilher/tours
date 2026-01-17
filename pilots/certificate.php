<?php
// pilots/certificate.php - CORRIGIDO
// Define que este script não deve enviar erros HTML para o navegador, pois quebraria o PDF
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// --- 1. CONFIGURAÇÃO DE CAMINHOS E BUFFERS ---
// Define o caminho da fonte ANTES de carregar a biblioteca
define('FPDF_FONTPATH', __DIR__ . '/font/');

// Inicia o buffer para capturar qualquer "sujeira" que o WordPress soltar
ob_start();

// Carrega o WordPress
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { 
    require_once $wpLoadPath; 
} else {
    // Fallback de segurança se não achar o WP, para não dar erro 500 sem aviso
    ob_end_clean();
    die("Erro: WordPress não encontrado em $wpLoadPath");
}

if (!is_user_logged_in()) { 
    ob_end_clean();
    die('Acesso restrito.'); 
}

$current_user = wp_get_current_user();
$wp_user_id = $current_user->ID;

require '../config/db.php';

// --- 2. VALIDAÇÃO DE DADOS ---
$tour_id = filter_input(INPUT_GET, 'tour_id', FILTER_VALIDATE_INT);
if (!$tour_id) { ob_end_clean(); die("ID Inválido"); }

// Verificar progresso
$stmt = $pdo->prepare("SELECT * FROM tour_progress WHERE pilot_id = ? AND tour_id = ? AND status = 'Completed'");
$stmt->execute([$wp_user_id, $tour_id]);
$progress = $stmt->fetch();

if (!$progress) {
    ob_end_clean();
    die("Certificado indisponível: Você ainda não completou este Tour.");
}

$stmtTour = $pdo->prepare("SELECT * FROM tour_tours WHERE id = ?");
$stmtTour->execute([$tour_id]);
$tour = $stmtTour->fetch();

// --- 3. PREPARAÇÃO DO PDF ---

// Limpa qualquer saída que o WordPress ou includes tenham gerado até agora
// Isso remove espaços em branco, erros de notice, ou HTML de plugins
if (ob_get_length()) ob_end_clean();

// Carrega FPDF (Use require_once para evitar erro de redeclaração se um plugin já carregou)
require_once('fpdf.php');

// Renomeei a classe para KaflyPDF para evitar conflito se outro plugin usar "class PDF"
class KaflyPDF extends FPDF {
    function Header() {
        // Nada aqui
    }
    
    function Footer() {
        $this->SetY(-12);
        // Tenta usar Arial, se falhar usa Helvetica (padrão do core)
        $this->SetFont('Helvetica','I',8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0,10,utf8_decode('Documento gerado automaticamente pelo sistema Kafly Tours - ' . date('d/m/Y H:i')),0,0,'C');
    }
}

// Cria o PDF
$pdf = new KaflyPDF('L','mm','A4'); 
$pdf->SetAutoPageBreak(false); 
$pdf->AddPage();

// --- 4. DESIGN ---

// Molduras
$pdf->SetLineWidth(2);
$pdf->SetDrawColor(218, 165, 32); // Dourado
$pdf->Rect(10, 10, 277, 190);
$pdf->SetLineWidth(0.5);
$pdf->SetDrawColor(0, 0, 0); // Preto
$pdf->Rect(12, 12, 273, 186);

// Título
$pdf->SetY(40);
// Tenta Times, fallback para padrão se der erro de arquivo
$pdf->SetFont('Times','B',36); 
$pdf->SetTextColor(25, 25, 112); 
$pdf->Cell(0,15,utf8_decode('CERTIFICADO DE CONCLUSÃO'),0,1,'C');

// Texto Intro
$pdf->SetY(65);
$pdf->SetFont('Helvetica','',14); // Mudado de Arial para Helvetica (são iguais no FPDF e evita erro de arquivo map)
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0,10,utf8_decode('Certificamos que o Comandante'),0,1,'C');

// Nome Piloto
$pdf->SetY(80);
$pdf->SetFont('Times','B',32);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0,15,utf8_decode($current_user->display_name),0,1,'C');

$pdf->SetY(95);
$pdf->SetFont('Helvetica','',12);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0,10,utf8_decode('(ID: ' . str_pad($wp_user_id, 4, '0', STR_PAD_LEFT) . ')'),0,1,'C');

// Texto Tour
$pdf->SetY(115);
$pdf->SetFont('Helvetica','',14);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0,10,utf8_decode('Completou com êxito todas as etapas do evento:'),0,1,'C');

// Nome do Tour
$pdf->SetY(130);
$pdf->SetFont('Times','B',28);
$pdf->SetTextColor(218, 165, 32); 
$pdf->Cell(0,15,utf8_decode($tour['title']),0,1,'C');

// Assinaturas
$ySign = 165;
$pdf->SetDrawColor(50, 50, 50);
$pdf->SetLineWidth(0.5);

// Assinatura 1
$pdf->Line(60, $ySign, 110, $ySign);
$rubricaDiretorPath = __DIR__ . '/../assets/signatures/rubrica_diretor.png';
if (file_exists($rubricaDiretorPath)) {
    $pdf->Image($rubricaDiretorPath, 70, $ySign - 15, 30); 
}
$pdf->SetFont('Helvetica','B',10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Text(63, $ySign+5, utf8_decode("Diretor de Operações"));

// Hash
$pdf->SetXY(120, $ySign - 10);
$pdf->SetFont('Courier','',8);
$pdf->SetTextColor(150, 150, 150);
$hash = md5($progress['completed_at'].$wp_user_id);
$pdf->MultiCell(55, 4, utf8_decode("HASH DE VALIDAÇÃO:\n".$hash."\n".date('d/m/Y', strtotime($progress['completed_at']))), 0, 'C');

// Assinatura 2
$pdf->Line(190, $ySign, 240, $ySign);
$rubricaEventosPath = __DIR__ . '/../assets/signatures/rubrica_eventos.png';
if (file_exists($rubricaEventosPath)) {
    $pdf->Image($rubricaEventosPath, 200, $ySign - 12, 30); 
}
$pdf->SetFont('Helvetica','B',10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Text(198, $ySign+5, "Gestor de Eventos");

// --- 5. SAÍDA FINAL ---
// Garante novamente que o buffer está limpo antes de enviar o binário
if (ob_get_length()) ob_end_clean();

$pdf->Output('I', 'Certificado_Tour_'.$tour_id.'.pdf'); 
exit;
?>