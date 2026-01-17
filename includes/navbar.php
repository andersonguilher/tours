<?php
// includes/navbar.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Recupera dados básicos para exibir no topo (se disponível)
$nav_callsign = 'PILOTO';
if (function_exists('wp_get_current_user')) {
    $u = wp_get_current_user();
    $nav_callsign = strtoupper($u->user_login);
}
?>
<nav class="h-16 bg-slate-950 border-b border-slate-800 flex justify-between items-center px-6 sticky top-0 z-50 shadow-lg font-sans">
    <div class="flex items-center gap-2">
        <a href="index.php" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-earth-americas text-blue-500 text-xl"></i>
            <span class="font-bold text-lg tracking-widest text-white">SKY<span class="text-blue-500">TOURS</span></span>
        </a>
    </div>
    
    <div class="hidden md:flex gap-6 text-sm font-bold text-slate-400">
        <a href="index.php" class="hover:text-white transition no-underline">MISSÕES</a>
        <a href="rankings.php" class="hover:text-white transition no-underline">RANKING</a>
        <a href="passport_book.php" class="hover:text-white transition no-underline text-blue-400">PASSAPORTE</a>
    </div>

    <div class="flex items-center gap-6 text-sm">
        <div class="text-right hidden sm:block leading-tight">
            <div class="text-[10px] text-slate-500 uppercase">Bem-vindo</div>
            <div class="font-bold font-mono text-yellow-400"><?php echo $nav_callsign; ?></div>
        </div>
        <a href="../../" class="text-slate-400 hover:text-white transition no-underline">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
    </div>
</nav>