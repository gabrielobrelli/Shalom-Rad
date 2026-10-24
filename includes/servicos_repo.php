<?php
/**
 * ============================================================================
 * servicos_repo.php — Camada de acesso a dados da AGENDA DE SERVIÇOS.
 * ============================================================================
 * Índice rápido:
 *   - criar_servico()              → grava o serviço + a lista de pagamentos
 *   - buscar_servico()             → um serviço com seus pagamentos
 *   - listar_servicos_do_dia()     → usado na visão "dia" do calendário
 *   - resumo_por_dia_no_periodo()  → total (R$) e contagem por dia, usado
 *                                    nas visões "mês" e "semana" do calendário
 *   - excluir_servico()            → apaga o serviço e seus pagamentos (CASCADE)
 *   - totais_do_periodo()          → soma para os relatórios (dia/semana/
 *                                    mês/semestre/ano), com detalhe por
 *                                    forma de pagamento
 * ============================================================================
 */
require_once __DIR__ . '/database.php';

/**
 * Cria um serviço novo e grava, na mesma transação, os pagamentos
 * associados (pode ser um ou vários — ex.: metade no Pix, metade em
 * dinheiro).
 */
function criar_servico(string $data, string $carroCliente, string $descricao, array $profissionalIds, float $valorCobrado, float $desconto, array $pagamentos, string $observacaoAdm = ''): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO servicos (data_servico, carro_cliente, descricao, observacao_adm, valor_cobrado, desconto)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$data, trim($carroCliente), trim($descricao) ?: null, trim($observacaoAdm) ?: null, $valorCobrado, $desconto]);
        $servicoId = (int)$pdo->lastInsertId();

        $stmtProf = $pdo->prepare('INSERT IGNORE INTO servico_profissionais (servico_id, profissional_id) VALUES (?, ?)');
        foreach (array_unique(array_filter(array_map('intval', $profissionalIds))) as $profissionalId) {
            $stmtProf->execute([$servicoId, $profissionalId]);
        }

        $formasValidas = ['pix', 'dinheiro', 'debito', 'credito', 'outro'];
        $stmtPag = $pdo->prepare(
            'INSERT INTO servico_pagamentos (servico_id, forma, valor, parcelas, conta) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($pagamentos as $p) {
            $forma = in_array($p['forma'] ?? '', $formasValidas, true) ? $p['forma'] : 'outro';
            $valor = (float)($p['valor'] ?? 0);
            if ($valor <= 0) continue;
            $parcelas = ($forma === 'credito') ? max(1, (int)($p['parcelas'] ?? 1)) : null;
            $conta = trim((string)($p['conta'] ?? '')) ?: null;
            $stmtPag->execute([$servicoId, $forma, $valor, $parcelas, $conta]);
        }

        $pdo->commit();
        return $servicoId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Atualiza um serviço já existente (dados + pagamentos), desde que ainda
 * esteja dentro da janela de edição permitida (ver pode_editar_servico()).
 * O campo `criado_em` (data/hora em que o serviço foi lançado no sistema)
 * NUNCA é alterado aqui — só o conteúdo do serviço muda.
 */
function atualizar_servico(int $id, string $data, string $carroCliente, string $descricao, array $profissionalIds, float $valorCobrado, float $desconto, array $pagamentos, string $observacaoAdm = ''): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE servicos SET data_servico = ?, carro_cliente = ?, descricao = ?, observacao_adm = ?, valor_cobrado = ?, desconto = ?
             WHERE id = ?'
        );
        $stmt->execute([$data, trim($carroCliente), trim($descricao) ?: null, trim($observacaoAdm) ?: null, $valorCobrado, $desconto, $id]);

        $pdo->prepare('DELETE FROM servico_profissionais WHERE servico_id = ?')->execute([$id]);
        $stmtProf = $pdo->prepare('INSERT IGNORE INTO servico_profissionais (servico_id, profissional_id) VALUES (?, ?)');
        foreach (array_unique(array_filter(array_map('intval', $profissionalIds))) as $profissionalId) {
            $stmtProf->execute([$id, $profissionalId]);
        }

        $pdo->prepare('DELETE FROM servico_pagamentos WHERE servico_id = ?')->execute([$id]);

        $formasValidas = ['pix', 'dinheiro', 'debito', 'credito', 'outro'];
        $stmtPag = $pdo->prepare('INSERT INTO servico_pagamentos (servico_id, forma, valor, parcelas, conta) VALUES (?, ?, ?, ?, ?)');
        foreach ($pagamentos as $p) {
            $forma = in_array($p['forma'] ?? '', $formasValidas, true) ? $p['forma'] : 'outro';
            $valor = (float)($p['valor'] ?? 0);
            if ($valor <= 0) continue;
            $parcelas = ($forma === 'credito') ? max(1, (int)($p['parcelas'] ?? 1)) : null;
            $conta = trim((string)($p['conta'] ?? '')) ?: null;
            $stmtPag->execute([$id, $forma, $valor, $parcelas, $conta]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Diz se um serviço ainda pode ser editado: só é permitido dentro das
 * primeiras 24 horas depois de `criado_em` (quando foi lançado no
 * sistema — não a data do serviço em si, que pode ter sido no passado).
 * Passadas as 24h, a edição é desligada — só resta excluir.
 */
function pode_editar_servico(array $servico): bool
{
    $lancadoEm = strtotime($servico['criado_em']);
    if ($lancadoEm === false) return false;
    return (time() - $lancadoEm) < (24 * 60 * 60);
}

function buscar_servico(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT s.*,
                (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
                 FROM servico_profissionais sp
                 INNER JOIN profissionais p ON p.id = sp.profissional_id
                 WHERE sp.servico_id = s.id) AS profissionais_nomes
         FROM servicos s
         WHERE s.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $servico = $stmt->fetch();
    if (!$servico) return null;

    $stmtPag = db()->prepare('SELECT * FROM servico_pagamentos WHERE servico_id = ? ORDER BY id ASC');
    $stmtPag->execute([$id]);
    $servico['pagamentos'] = $stmtPag->fetchAll();

    $stmtProf = db()->prepare('SELECT profissional_id FROM servico_profissionais WHERE servico_id = ?');
    $stmtProf->execute([$id]);
    $servico['profissional_ids'] = array_map('intval', $stmtProf->fetchAll(PDO::FETCH_COLUMN));

    return $servico;
}

/** Lista os serviços de um dia específico (visão "dia" do calendário). */
function listar_servicos_do_dia(string $data): array
{
    $stmt = db()->prepare(
        "SELECT s.*,
                (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
                 FROM servico_profissionais sp
                 INNER JOIN profissionais p ON p.id = sp.profissional_id
                 WHERE sp.servico_id = s.id) AS profissionais_nomes
         FROM servicos s
         WHERE s.data_servico = ? ORDER BY s.criado_em ASC"
    );
    $stmt->execute([$data]);
    $servicos = $stmt->fetchAll();

    foreach ($servicos as &$s) {
        $stmtPag = db()->prepare('SELECT * FROM servico_pagamentos WHERE servico_id = ? ORDER BY id ASC');
        $stmtPag->execute([$s['id']]);
        $s['pagamentos'] = $stmtPag->fetchAll();
    }
    return $servicos;
}

/**
 * Lista todos os serviços dentro de um período (não só um dia) — usado
 * no relatório de fechamento (admin/fechamento.php).
 */
function listar_servicos_periodo(string $dataInicio, string $dataFim): array
{
    $stmt = db()->prepare(
        "SELECT s.*,
                (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
                 FROM servico_profissionais sp
                 INNER JOIN profissionais p ON p.id = sp.profissional_id
                 WHERE sp.servico_id = s.id) AS profissionais_nomes
         FROM servicos s
         WHERE s.data_servico BETWEEN ? AND ? ORDER BY s.data_servico ASC, s.criado_em ASC"
    );
    $stmt->execute([$dataInicio, $dataFim]);
    return $stmt->fetchAll();
}

/**
 * Resumo (total em R$ e quantidade de serviços) agrupado por dia, dentro
 * de um período — usado para desenhar os números pequenos nas células do
 * calendário nas visões "mês" e "semana".
 * Retorna um array associativo: ['2026-08-27' => ['total' => 350.00, 'qtd' => 3], ...]
 */
function resumo_por_dia_no_periodo(string $dataInicio, string $dataFim): array
{
    $stmt = db()->prepare(
        'SELECT data_servico, COUNT(*) AS qtd, SUM(valor_cobrado - desconto) AS total
         FROM servicos
         WHERE data_servico BETWEEN ? AND ?
         GROUP BY data_servico'
    );
    $stmt->execute([$dataInicio, $dataFim]);

    $resumo = [];
    foreach ($stmt->fetchAll() as $linha) {
        $resumo[$linha['data_servico']] = ['total' => (float)$linha['total'], 'qtd' => (int)$linha['qtd']];
    }
    return $resumo;
}

function excluir_servico(int $id): void
{
    $stmt = db()->prepare('DELETE FROM servicos WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Totais financeiros de um período (usado nos relatórios de dia, semana,
 * mês, semestre e ano). Retorna o total bruto, o desconto total, o
 * líquido, a quantidade de serviços e o detalhamento por forma de
 * pagamento (Pix, dinheiro, débito, crédito, outro).
 */
function totais_do_periodo(string $dataInicio, string $dataFim): array
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_cobrado), 0) AS bruto, COALESCE(SUM(desconto), 0) AS descontos
         FROM servicos WHERE data_servico BETWEEN ? AND ?'
    );
    $stmt->execute([$dataInicio, $dataFim]);
    $geral = $stmt->fetch();

    $stmtFormas = db()->prepare(
        'SELECT sp.forma, COALESCE(SUM(sp.valor), 0) AS total
         FROM servico_pagamentos sp
         INNER JOIN servicos s ON s.id = sp.servico_id
         WHERE s.data_servico BETWEEN ? AND ?
         GROUP BY sp.forma'
    );
    $stmtFormas->execute([$dataInicio, $dataFim]);
    $porForma = [];
    foreach ($stmtFormas->fetchAll() as $linha) {
        $porForma[$linha['forma']] = (float)$linha['total'];
    }

    return [
        'quantidade' => (int)$geral['qtd'],
        'bruto' => (float)$geral['bruto'],
        'descontos' => (float)$geral['descontos'],
        'liquido' => (float)$geral['bruto'] - (float)$geral['descontos'],
        'por_forma' => $porForma,
    ];
}

/** Rótulo amigável para cada forma de pagamento. */
function rotulo_forma_pagamento(string $forma): string
{
    $map = [
        'pix' => 'Pix',
        'dinheiro' => 'Dinheiro',
        'debito' => 'Débito',
        'credito' => 'Crédito',
        'outro' => 'Outro',
    ];
    return $map[$forma] ?? 'Outro';
}

/**
 * Monta uma lista de períodos para o GRÁFICO COMPARATIVO
 * (admin/grafico.php), já com o total líquido de cada um calculado.
 *
 * - tipo "semana": as últimas $quantidade semanas (a mais recente por último).
 * - tipo "mes": os últimos $quantidade meses.
 * - tipo "personalizado": divide o intervalo [$dataInicio, $dataFim] em
 *   fatias diárias (se o intervalo tiver até 31 dias) ou semanais (se for
 *   maior), pra sempre gerar um gráfico legível.
 */
function construir_periodos_comparacao(string $tipo, int $quantidade, ?string $dataInicio = null, ?string $dataFim = null): array
{
    $periodos = [];

    if ($tipo === 'semana') {
        $hoje = date('Y-m-d');
        $diaSemana = (int)date('w', strtotime($hoje));
        $inicioSemanaAtual = date('Y-m-d', strtotime($hoje . ' -' . $diaSemana . ' days'));
        for ($i = $quantidade - 1; $i >= 0; $i--) {
            $inicio = date('Y-m-d', strtotime($inicioSemanaAtual . " -" . ($i * 7) . " days"));
            $fim = date('Y-m-d', strtotime($inicio . ' +6 days'));
            $periodos[] = ['label' => date('d/m', strtotime($inicio)), 'inicio' => $inicio, 'fim' => $fim];
        }
    } elseif ($tipo === 'mes') {
        $nomesMes = ['', 'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        for ($i = $quantidade - 1; $i >= 0; $i--) {
            $referencia = strtotime(date('Y-m-01') . " -$i months");
            $inicio = date('Y-m-01', $referencia);
            $fim = date('Y-m-t', $referencia);
            $periodos[] = ['label' => $nomesMes[(int)date('n', $referencia)] . '/' . date('y', $referencia), 'inicio' => $inicio, 'fim' => $fim];
        }
    } else { // personalizado
        $inicio = $dataInicio ?: date('Y-m-d', strtotime('-30 days'));
        $fim = $dataFim ?: date('Y-m-d');
        $dias = (strtotime($fim) - strtotime($inicio)) / 86400;

        if ($dias <= 31) {
            for ($d = 0; $d <= $dias; $d++) {
                $data = date('Y-m-d', strtotime($inicio . " +$d days"));
                $periodos[] = ['label' => date('d/m', strtotime($data)), 'inicio' => $data, 'fim' => $data];
            }
        } else {
            $cursor = $inicio;
            while (strtotime($cursor) <= strtotime($fim)) {
                $fimFatia = min(date('Y-m-d', strtotime($cursor . ' +6 days')), $fim);
                $periodos[] = ['label' => date('d/m', strtotime($cursor)), 'inicio' => $cursor, 'fim' => $fimFatia];
                $cursor = date('Y-m-d', strtotime($fimFatia . ' +1 day'));
            }
        }
    }

    foreach ($periodos as &$p) {
        $totais = totais_do_periodo($p['inicio'], $p['fim']);
        $p['total'] = $totais['liquido'];
        $p['quantidade'] = $totais['quantidade'];
    }

    return $periodos;
}

/**
 * Gera um gráfico de barras simples em SVG a partir de uma lista de
 * períodos (ver construir_periodos_comparacao()). Usado tanto na tela
 * quanto na versão impressa — SVG é leve, escalável e imprime bem sem
 * precisar de nenhuma biblioteca de gráficos em JavaScript.
 */
function renderizar_grafico_svg(array $periodos, int $largura = 900, int $altura = 320): string
{
    if (empty($periodos)) {
        return '<p>Sem dados para exibir.</p>';
    }

    $margemBaixo = 46;
    $margemTopo = 20;
    $areaAltura = $altura - $margemBaixo - $margemTopo;
    $maior = max(array_column($periodos, 'total')) ?: 1;

    $qtdBarras = count($periodos);
    $larguraBarra = max(6, min(60, ($largura / $qtdBarras) * 0.6));
    $espaco = $largura / $qtdBarras;

    $svg = '<svg viewBox="0 0 ' . $largura . ' ' . $altura . '" xmlns="http://www.w3.org/2000/svg" class="grafico-svg">';
    $svg .= '<line x1="0" y1="' . ($altura - $margemBaixo) . '" x2="' . $largura . '" y2="' . ($altura - $margemBaixo) . '" stroke="#dbe1ea" stroke-width="1"/>';

    foreach ($periodos as $i => $p) {
        $alturaBarra = $maior > 0 ? ($p['total'] / $maior) * $areaAltura : 0;
        $x = $i * $espaco + ($espaco - $larguraBarra) / 2;
        $y = $altura - $margemBaixo - $alturaBarra;
        $cor = $p['total'] > 0 ? '#e0640f' : '#dbe1ea';

        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($larguraBarra, 1) . '" height="' . round($alturaBarra, 1) . '" rx="3" fill="' . $cor . '">'
              . '<title>' . htmlspecialchars($p['label']) . ': ' . htmlspecialchars(formatar_dinheiro($p['total'])) . '</title></rect>';

        if ($p['total'] > 0) {
            $svg .= '<text x="' . round($x + $larguraBarra / 2, 1) . '" y="' . round($y - 6, 1) . '" font-size="10" text-anchor="middle" fill="#0f2c4f" font-weight="700">'
                  . htmlspecialchars(number_format($p['total'], 0, ',', '.')) . '</text>';
        }

        $svg .= '<text x="' . round($x + $larguraBarra / 2, 1) . '" y="' . ($altura - $margemBaixo + 16) . '" font-size="10" text-anchor="middle" fill="#5c6b7a">'
              . htmlspecialchars($p['label']) . '</text>';
    }

    $svg .= '</svg>';
    return $svg;
}
