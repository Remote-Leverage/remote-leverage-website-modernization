<?php

use App\Support\BlockDefaults;

/**
 * Title: Spanish - Más Allá del Asistente Virtual
 * Slug: remote-leverage/spanish-beyond-virtual-assistant
 * Categories: remote-leverage
 * Description: Spanish counterpart of beyond-virtual-assistant — four department cards.
 */
$titles = [
    'Asistentes de Admin<br>y Ejecutivos',
    'Asistentes de Salud y Medicina',
    'Talento en Ventas<br>y Marketing',
    'Profesionales de<br>Operaciones y Finanzas',
];
$descs = [
    'Soporte ejecutivo para fundadores y equipos ocupados.',
    'Profesionales de la salud que apoyan a clínicas y consultorios.',
    'Profesionales enfocados en crecimiento, leads e ingresos.',
    'Expertos en finanzas, operaciones y soporte empresarial.',
];

// Same card art as the English section; only the copy is localised.
$cards = array_map(
    static fn (array $source, string $title, string $desc) => [...$source, 'title' => $title, 'desc' => $desc],
    BlockDefaults::departmentCards(),
    $titles,
    $descs,
);
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                Más Allá del "Asistente Virtual"
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                Nos especializamos en encontrar profesionales de habla inglesa a nivel global para roles que requieren una ejecución de alto nivel.
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderDepartmentCards([], $cards) ?>
</div>
<!-- /wp:group -->
