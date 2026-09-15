<?php

use App\Support\BlockDefaults;

/**
 * Title: Spanish - Por Qué las Empresas Eligen Remote Leverage
 * Slug: remote-leverage/spanish-why-companies-choose
 * Categories: remote-leverage
 * Description: Spanish counterpart of why-companies-choose — four metric cards over the DIY comparison table.
 */
$cards = array_map(
    static fn (array $source, array $copy) => [...$source, ...$copy],
    BlockDefaults::featureCards('4'),
    [
        ['title' => '$6-10 /hr', 'desc' => 'Accede a profesionales con experiencia a tarifas altamente competitivas. La mayoría de los roles administrativos, de soporte, ventas y marketing pueden cubrirse dentro de este rango.'],
        ['title' => '70% Menos Costos', 'desc' => 'Reduce los costos de contratación sin sacrificar calidad. Reinvierte los ahorros en crecimiento, marketing, desarrollo de producto o contrataciones adicionales.'],
        ['title' => 'Promedio de 4 Días', 'desc' => 'Desde abrir una vacante hasta revisar candidatos calificados en días. Nuestro proceso de reclutamiento está diseñado para ser rápido sin comprometer la calidad.'],
        ['title' => 'Evaluados por Calidad', 'desc' => 'Cada candidato es evaluado en fluidez del inglés, experiencia, habilidades de comunicación y experiencia específica del rol antes de llegar a tu bandeja de entrada.'],
    ],
);

$rows = [
    ['feature' => 'Tiempo de Contratar', 'diy' => '4-8 semanas', 'rl' => '72 horas'],
    ['feature' => 'Calidad', 'diy' => 'Al azar', 'rl' => 'Top 1% pre-evaluado'],
    ['feature' => 'Nómina e impuestos', 'diy' => 'DIY o abogado local costoso', 'rl' => 'Totalmente gestionable'],
    ['feature' => 'Riesgo Legal/Laboral', 'diy' => 'Alto - mala clasificación, leyes locales', 'rl' => 'Cero - cobertura en más de 170 países'],
    ['feature' => 'Cuotas continuas', 'diy' => 'A menudo 30-50% de margen mensual', 'rl' => 'Solo una cuota única'],
    ['feature' => 'Garantía de reemplazo', 'diy' => 'Ninguna', 'rl' => '12 meses, sin costos adicionales'],
    ['feature' => 'Reportes', 'diy' => 'Hojas de cálculo', 'rl' => 'Panel para gestionar tu equipo'],
];
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"},"spacing":{"margin":{"bottom":"3rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-huge-font-size" style="margin-bottom:3rem;letter-spacing:-0.03em;line-height:1.08">
        Por Qué las Empresas<br>Eligen Remote Leverage
    </h2>
    <!-- /wp:heading -->

    <?= BlockDefaults::renderFeatureCards('4', [], $cards) ?>

    <?= BlockDefaults::renderDataTable([], $rows) ?>
</div>
<!-- /wp:group -->
