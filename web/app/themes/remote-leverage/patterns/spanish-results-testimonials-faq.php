<?php

use App\Support\BlockDefaults;

/**
 * Title: Spanish - Resultados, No Promesas + Preguntas Frecuentes
 * Slug: remote-leverage/spanish-results-testimonials-faq
 * Categories: remote-leverage
 * Description: Spanish counterpart of results-testimonials-faq.
 *
 * The testimonial quotes stay in English: production leaves them untranslated, because
 * they are transcripts of real client videos.
 */
$faqs = [
    [
        'question' => '¿De qué países contratan?',
        'answer' => 'Nos enfocamos en cuatro regiones clave: Latinoamérica y el Caribe, Filipinas, Sudáfrica y Egipto.<br><br>Nuestros Asistentes Virtuales de Latinoamérica son especialmente populares entre empresas de EE. UU., gracias a:<br>• Fluidez excepcional en inglés con acento mínimo<br>• Fuerte alineación cultural con las prácticas empresariales de EE. UU.<br>• Superposición conveniente de zona horaria con Norteamérica<br><br>Te guiaremos sobre qué región se ajusta mejor a tus necesidades específicas, pero la decisión final siempre es tuya.',
    ],
    [
        'question' => '¿Cómo funcionan los impuestos y la nómina al contratar Asistentes Virtuales?',
        'answer' => 'Nuestra empresa aliada se encarga de todos los requisitos de nómina y cumplimiento normativo de tu Asistente Virtual. Esto significa que puedes enfocarte en hacer crecer tu negocio mientras ellos manejan:<br>• Cumplimiento fiscal<br>• Procesamiento de nómina<br>• Requisitos legales<br>• Regulaciones de pagos internacionales<br><br>Es una solución simple y sin preocupaciones que garantiza que todo se gestione de forma correcta y legal.',
    ],
    [
        'question' => '¿Cómo les pagan?',
        'answer' => 'Es simple: cobramos una cuota única fija, pero solo después de que hayas encontrado a tu candidato ideal. Cualquier pago por hora que decidas pagar va directamente al Asistente Virtual que contrates.',
    ],
    [
        'question' => '¿Cuál es la diferencia entre una Agencia de Personal (Staffing) y una Agencia de Reclutamiento?',
        'answer' => 'Las agencias de staffing cobran cuotas mensuales, pero solo pagan una pequeña parte a los Asistentes Virtuales. Esto a menudo resulta en talento de menor calidad, ya que los VAs calificados evitan acuerdos donde las agencias se quedan con una gran parte de sus ingresos.<br><br>En Remote Leverage, cobramos solo una cuota fija después de que contratas. Tu Asistente Virtual recibe el 100% de lo que le pagas directamente. Esto atrae talento de mayor calidad y elimina los costos continuos de intermediarios.',
    ],
    [
        'question' => '¿Qué pasa si tengo preguntas y necesito ayuda después de contratar?',
        'answer' => 'Después de contratar a tu Asistente Virtual, tendrás acceso a un Gerente de Éxito del Cliente dedicado que te ayudará a garantizar tu éxito. Están aquí para ayudarte con:<br>• Revisión de desempeño<br>• Seguimiento de progreso<br>• Orientación en capacitación<br>• Cualquier otra pregunta o solicitud',
    ],
    [
        'question' => '¿Qué pasa si no resulta ser la persona adecuada?',
        'answer' => 'Aunque es poco común tener problemas ya que los candidatos son evaluados minuciosamente tanto por nuestro equipo como por ti, entendemos la importancia de encontrar el ajuste correcto. Por eso ofrecemos:<br>• Garantía de reemplazo de 12 meses sin costo adicional<br>• Entrevistas ilimitadas de candidatos para asegurar que encuentres la mejor opción<br><br>Este proceso de doble evaluación (por nosotros y por ti) ayuda a garantizar contrataciones de calidad.',
    ],
    [
        'question' => '¿Cómo es su inglés y sus habilidades de comunicación?',
        'answer' => 'Mantenemos estándares extremadamente altos de fluidez en inglés. Así es como lo garantizamos:<br>• Todos los candidatos deben enviar una grabación de voz en inglés<br>• Revisamos cientos de solicitudes diariamente, y solo seleccionamos a quienes tienen inglés fluido y acento mínimo<br>• Solo los mejores comunicadores logran pasar nuestro filtro<br><br>Este riguroso proceso de evaluación significa que trabajarás con un Asistente Virtual que se comunica de forma clara y profesional.',
    ],
    [
        'question' => '¿Puedo empezar con medio tiempo?',
        'answer' => 'Sí, puedes empezar tanto con medio tiempo como con tiempo completo. El mínimo es de 20 horas por semana, ya que nuestros Asistentes Virtuales más calificados prefieren puestos estables con horarios consistentes.',
    ],
    [
        'question' => '¿En qué zona horaria trabajarán?',
        'answer' => 'Tu Asistente Virtual trabajará según tu horario y zona horaria. Están acostumbrados al horario de EE. UU., y tú puedes establecer las horas de trabajo que mejor se ajusten a tus necesidades.',
    ],
    [
        'question' => '¿Cuánto cuesta en promedio un Asistente Virtual?',
        'answer' => 'Las tarifas por hora del Asistente Virtual dependen de las habilidades y experiencia que tenga, y también de la región desde la que estés contratando.<br>• Nivel de Entrada: $6-$10 por hora<br>• Alta Experiencia: $11-$15 por hora<br><br>La tarifa por hora que acuerdes pagar va directamente a tu Asistente Virtual — no cobramos comisiones sobre su pago.',
    ],
];

$faqData = ['headline' => 'Preguntas Frecuentes', '_headline' => 'field_accordion_faq_block_headline'];
BlockDefaults::encodeRepeater('faqs', 'field_accordion_faq_block_faqs', $faqs, $faqData);
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"6rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:6rem;padding-bottom:6rem">
    <!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"3.5rem"}}}} -->
    <div class="wp-block-columns" style="margin-bottom:3.5rem">
        <!-- wp:column {"width":"55%"} -->
        <div class="wp-block-column" style="flex-basis:55%">
            <!-- wp:heading {"level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.03em"}},"fontSize":"huge"} -->
            <h2 class="wp-block-heading has-huge-font-size" style="letter-spacing:-0.03em;line-height:1.08">
                Resultados, No Promesas
            </h2>
            <!-- /wp:heading -->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"45%"} -->
        <div class="wp-block-column" style="flex-basis:45%">
            <!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}}} -->
            <p style="line-height:1.6">
                No te quedes solo con nuestra palabra, escucha a los dueños de negocios que han contratado a través de Remote Leverage. ¡Descubre por qué la calidad hace toda la diferencia!
            </p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->

    <?= BlockDefaults::renderTestimonials() ?>

    <?= BlockDefaults::patternBlock('accordion-faq', $faqData) ?>
</div>
<!-- /wp:group -->
