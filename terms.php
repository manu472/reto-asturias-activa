<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

render_header('Terminos y condiciones', [
    'description' => 'Terminos y condiciones de uso de Reto Asturias Activa para cuentas, comunidad, rutas propuestas y plan premium.',
    'canonical' => 'terms.php',
]);
?>
<section class="hero institutional-hero">
    <span class="card-kicker">Condiciones</span>
    <h1>Terminos y condiciones de uso</h1>
    <p>Estas reglas definen el marco basico para usar la plataforma, participar en la comunidad y contratar, en su caso, el plan premium.</p>
</section>

<section class="card legal-document" style="margin-top: 14px;">
    <div class="legal-meta">Ultima actualizacion: 29/04/2026</div>
    <h2 class="section-title">1. Objeto del servicio</h2>
    <p class="content-prose">Reto Asturias Activa ofrece un entorno digital para consultar rutas de Asturias, gestionar actividad de usuario, participar en retos, compartir comentarios, proponer nuevas rutas y acceder a funcionalidades premium.</p>

    <h2 class="section-title">2. Cuenta de usuario</h2>
    <p class="content-prose">Para acceder a determinadas funciones es necesario registrarse y mantener la informacion de cuenta razonablemente actualizada. La persona usuaria es responsable de custodiar sus credenciales y de las acciones realizadas desde su cuenta.</p>

    <h2 class="section-title">3. Uso permitido</h2>
    <ul class="feature-list">
        <li>Utilizar el sitio con fines licitos y respetuosos.</li>
        <li>No introducir contenido ofensivo, fraudulento o engañoso.</li>
        <li>No manipular rankings, retos, comentarios o formularios de forma abusiva.</li>
        <li>No realizar acciones tecnicas que perjudiquen la estabilidad o seguridad del proyecto.</li>
    </ul>

    <h2 class="section-title">4. Contenido aportado por usuarios</h2>
    <p class="content-prose">Las personas usuarias pueden enviar comentarios e incidencias. Las propuestas de rutas forman parte de Premium y el sitio podra moderar, rechazar o retirar contenidos cuando no se ajusten a estas condiciones, a la legalidad o a los criterios editoriales de la plataforma.</p>

    <h2 class="section-title">5. Rutas e informacion orientativa</h2>
    <p class="content-prose">La informacion mostrada en fichas, mapas o descargas tiene caracter orientativo. La preparacion real de una salida corresponde a la persona usuaria, que debe valorar condiciones meteorologicas, estado del terreno, nivel fisico y seguridad antes de iniciar la ruta.</p>

    <h2 class="section-title">6. Premium y pagos</h2>
    <p class="content-prose">El plan premium da acceso a ventajas adicionales definidas en la plataforma, como herramientas de planificacion, formatos de descarga avanzados y mejoras de progreso. El alta, la renovacion y la cancelacion se regiran por las condiciones mostradas durante el proceso de contratacion.</p>

    <h2 class="section-title">7. Suspension o cancelacion</h2>
    <p class="content-prose">El sitio podra limitar o suspender el acceso a usuarios que incumplan estas condiciones, afecten negativamente a la comunidad o hagan un uso abusivo del servicio.</p>

    <h2 class="section-title">8. Modificaciones</h2>
    <p class="content-prose">La plataforma puede evolucionar y, con ello, actualizar estas condiciones para adaptarlas a nuevas funcionalidades, integraciones o necesidades operativas.</p>
</section>
<?php render_footer(); ?>
