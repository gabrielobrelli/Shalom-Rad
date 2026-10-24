<?php
/**
 * footer.php — Rodapé compartilhado de todas as páginas do site.
 * Assim como header.php, espera que $baseAssets já esteja definida
 * ('' na raiz, '../' dentro de /admin/).
 */
$baseAssets = $baseAssets ?? '';
?>
<footer class="site-footer no-print">
    <div class="faixa-diagonal"></div>
    <div class="site-footer__inner">
        <div class="site-footer__coluna">
            <strong>Shalom Radiadores</strong>
            <p>Radiadores, recondicionamento e manutenção para veículos leves e pesados.</p>
        </div>
        <div class="site-footer__coluna">
            <strong>Atendimento</strong>
            <p>📱 WhatsApp: (14) 98127-6780</p>
        </div>
        <div class="site-footer__coluna">
            <strong>Sistema interno</strong>
            <p><a href="<?= $baseAssets ?>login.php">🔒 Área da administração</a></p>
        </div>
    </div>
    <div class="site-footer__copy">© <?= date('Y') ?> Shalom Radiadores — Todos os direitos reservados.</div>
</footer>
</body>
</html>
