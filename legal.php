<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

render_header('Centro legal', [
    'description' => 'Centro legal de Reto Asturias Activa con aviso legal, politica de privacidad, politica de cookies y terminos de uso.',
    'canonical' => 'legal.php',
]);
?>
<section class="hero institutional-hero">
    <span class="card-kicker">Legal</span>
    <h1>Marco legal y documental del proyecto</h1>
    <p>Esta area agrupa la documentacion esencial del sitio para aportar seriedad, transparencia y una base coherente con el funcionamiento de la plataforma.</p>
</section>

<section class="grid grid-2 legal-grid" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Documentos disponibles</h2>
        <div class="footer-links legal-links">
            <a href="<?= e(url('legal.php')) ?>">Aviso legal y marco general</a>
            <a href="<?= e(url('privacy.php')) ?>">Politica de privacidad</a>
            <a href="<?= e(url('cookies.php')) ?>">Politica de cookies</a>
            <a href="<?= e(url('terms.php')) ?>">Terminos y condiciones de uso</a>
        </div>
    </article>
    <article class="card premium-surface">
        <h2 class="section-title">Objetivo de esta seccion</h2>
        <p class="content-prose">Una web con usuarios, cuentas, comentarios, propuestas de rutas y suscripcion premium necesita explicar de forma clara como funciona, que datos trata y cuales son las reglas basicas de uso.</p>
        <p class="content-prose">Este bloque legal esta pensado como base solida para el proyecto. Antes de una explotacion comercial real, conviene revisar y completar los datos identificativos definitivos y adaptar el texto a la situacion juridica exacta.</p>
    </article>
</section>

<section class="card" style="margin-top: 14px;">
    <span class="card-kicker">Aviso legal</span>
    <h2 class="section-title">Identificacion y finalidad del sitio</h2>
    <div class="content-prose">
        <p><strong>Titular del proyecto:</strong> Reto Asturias Activa, plataforma digital orientada a rutas, actividad al aire libre y participacion de usuarios dentro del territorio de Asturias.</p>
        <p><strong>Naturaleza del sitio:</strong> proyecto web con componente academico y enfoque de producto digital. Si en el futuro se explota comercialmente, deberan completarse o actualizarse los datos identificativos definitivos del responsable.</p>
        <p><strong>Finalidad principal:</strong> ofrecer fichas de rutas, mapas, retos, ranking, contenido editorial, herramientas premium y espacios de participacion de usuarios.</p>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 14px;">
    <article class="card">
        <h2 class="section-title">Propiedad intelectual</h2>
        <p class="content-prose">El diseno, la marca, la estructura del sitio, los desarrollos tecnicos y los contenidos propios del proyecto estan protegidos por la normativa aplicable en materia de propiedad intelectual e industrial.</p>
        <p class="content-prose">Las personas usuarias no pueden reproducir, distribuir o reutilizar contenidos del sitio con finalidad comercial sin autorizacion expresa, salvo en los casos permitidos por la ley.</p>
    </article>
    <article class="card">
        <h2 class="section-title">Responsabilidad de uso</h2>
        <p class="content-prose">El uso del sitio debe realizarse de forma diligente, licita y respetuosa. El titular podra limitar, suspender o cancelar accesos cuando detecte conductas contrarias a la normativa o al buen funcionamiento de la plataforma.</p>
        <p class="content-prose">La informacion sobre rutas tiene caracter orientativo y debe complementarse con criterio personal, revision de condiciones reales y preparacion suficiente antes de cada salida.</p>
    </article>
</section>

<section class="card" style="margin-top: 14px;">
    <h2 class="section-title">Enlaces, terceros y actualizacion</h2>
    <p class="content-prose">El sitio puede enlazar a servicios de terceros, por ejemplo mapas o pasarelas de pago. Cada servicio externo se rige por sus propias condiciones. Reto Asturias Activa podra actualizar estos textos para adaptarlos a nuevas funcionalidades, cambios normativos o mejoras del proyecto.</p>
    <div class="stack" style="margin-top: 12px;">
        <a class="button button-small" href="<?= e(url('privacy.php')) ?>">Privacidad</a>
        <a class="button secondary button-small" href="<?= e(url('cookies.php')) ?>">Cookies</a>
        <a class="button secondary button-small" href="<?= e(url('terms.php')) ?>">Terminos</a>
    </div>
</section>
<?php render_footer(); ?>
