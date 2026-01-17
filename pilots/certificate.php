<?php
// pilots/certificate.php - GERADOR DE CERTIFICADO PDF (FINAL COM 2 ASSINATURAS)
require('fpdf.php');
require '../config/db.php';

// --- SEGURANÇA E LOGIN ---
$wpLoadPath = __DIR__ . '/../../../wp-load.php';
if (file_exists($wpLoadPath)) { require_once $wpLoadPath; }
if (!is_user_logged_in()) { die('Acesso restrito.'); }

$current_user = wp_get_current_user();
$wp_user_id = $current_user->ID;

// --- DADOS DO TOUR ---
$tour_id = $_GET['tour_id'] ?? 0;
if ($tour_id == 0) die("ID Inválido");

// Verificar se o piloto REALMENTE completou o tour
$stmt = $pdo->prepare("SELECT * FROM pilot_tour_progress WHERE pilot_id = ? AND tour_id = ? AND status = 'Completed'");
$stmt->execute([$wp_user_id, $tour_id]);
$progress = $stmt->fetch();

if (!$progress) {
    die("Certificado indisponível: Você ainda não completou este Tour.");
}

// Buscar detalhes do Tour
$stmtTour = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmtTour->execute([$tour_id]);
$tour = $stmtTour->fetch();

// --- CLASSE PDF CUSTOMIZADA ---
class PDF extends FPDF {
    function Header() {
        // Nada aqui para permitir design livre
    }
    
    function Footer() {
        // Posicionamento fixo a 12mm do fundo
        $this->SetY(-12);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0,10,utf8_decode('Documento gerado automaticamente pelo sistema Kafly Tours - ' . date('d/m/Y H:i')),0,0,'C');
    }
}

// --- CONFIGURAÇÃO DA PÁGINA ---
// A4 Paisagem (Landscape): 297mm x 210mm
$pdf = new PDF('L','mm','A4'); 
$pdf->SetAutoPageBreak(false); // IMPEDE QUEBRA DE PÁGINA AUTOMÁTICA
$pdf->AddPage();

// 1. Moldura (Borda)
$pdf->SetLineWidth(2);
$pdf->SetDrawColor(218, 165, 32); // Dourado
$pdf->Rect(10, 10, 277, 190);
$pdf->SetLineWidth(0.5);
$pdf->SetDrawColor(0, 0, 0); // Preto
$pdf->Rect(12, 12, 273, 186);

// --- CONTEÚDO CENTRALIZADO ---

// 2. Título Principal
$pdf->SetY(40); // Posição Y fixa
$pdf->SetFont('Times','B',36);
$pdf->SetTextColor(25, 25, 112); // Midnight Blue
$pdf->Cell(0,15,utf8_decode('CERTIFICADO DE CONCLUSÃO'),0,1,'C');

// 3. Texto Introdutório
$pdf->SetY(65);
$pdf->SetFont('Arial','',14);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0,10,utf8_decode('Certificamos que o Comandante'),0,1,'C');

// 4. Nome do Piloto
$pdf->SetY(80);
$pdf->SetFont('Times','B',32);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0,15,utf8_decode($current_user->display_name),0,1,'C');

$pdf->SetY(95);
$pdf->SetFont('Arial','',12);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0,10,utf8_decode('(ID: ' . str_pad($wp_user_id, 4, '0', STR_PAD_LEFT) . ')'),0,1,'C');

// 5. Texto do Tour
$pdf->SetY(115);
$pdf->SetFont('Arial','',14);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0,10,utf8_decode('Completou com êxito todas as etapas do evento:'),0,1,'C');

// 6. Nome do Tour
$pdf->SetY(130);
$pdf->SetFont('Times','B',28);
$pdf->SetTextColor(218, 165, 32); // Dourado
$pdf->Cell(0,15,utf8_decode($tour['title']),0,1,'C');

// --- RODAPÉ E ASSINATURAS (POSIÇÃO FIXA NO FUNDO) ---
$ySign = 165; // Altura fixa para as linhas de assinatura (aprox 4.5cm do fundo)

// Configuração das Linhas
$pdf->SetDrawColor(50, 50, 50);
$pdf->SetLineWidth(0.5);

// ==========================================
// ASSINATURA 1 (ESQUERDA) - DIRETOR DE OPERAÇÕES
// ==========================================
$pdf->Line(60, $ySign, 110, $ySign); // Linha

// Rubrica 1 (Imagem)
$rubricaDiretorPath = '../assets/signatures/rubrica_diretor.png';
if (file_exists($rubricaDiretorPath)) {
    // Image(arquivo, x, y, largura)
    $pdf->Image($rubricaDiretorPath, 70, $ySign - 15, 30); 
}

// Texto Cargo
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Text(63, $ySign+5, utf8_decode("Diretor de Operações"));


// ==========================================
// SELO DIGITAL (CENTRO)
// ==========================================
$pdf->SetXY(120, $ySign - 10);
$pdf->SetFont('Courier','',8);
$pdf->SetTextColor(150, 150, 150);
$hash = md5($progress['completed_at'].$wp_user_id);
$pdf->MultiCell(55, 4, utf8_decode("HASH DE VALIDAÇÃO:\n".$hash."\n".date('d/m/Y', strtotime($progress['completed_at']))), 0, 'C');


// ==========================================
// ASSINATURA 2 (DIREITA) - GESTOR DE EVENTOS
// ==========================================
$pdf->Line(190, $ySign, 240, $ySign); // Linha

// Rubrica 2 (Imagem) - NOVA ADIÇÃO
$rubricaEventosPath = '../assets/signatures/rubrica_eventos.png';
if (file_exists($rubricaEventosPath)) {
    // Ajuste X=200 para centralizar na linha que vai de 190 a 240
    $pdf->Image($rubricaEventosPath, 200, $ySign - 12, 30); 
}

// Texto Cargo
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Text(198, $ySign+5, "Gestor de Eventos");

// --- SAÍDA ---
$pdf->Output('I', 'Certificado_Tour_'.$tour_id.'.pdf'); 
?>