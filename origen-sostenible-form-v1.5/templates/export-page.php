<?php
/**
 * Admin Page: Exportar CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'origen_form_submissions';
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

$export_url = wp_nonce_url(
    admin_url('admin.php?page=origen-form-export&origen_export_csv=1'),
    'origen_export_csv'
);
?>

<div class="wrap origen-admin-wrap">

    <div class="origen-admin-header">
        <h1>Exportar Formularios</h1>
        <span class="badge"><?php echo intval($total); ?> registros disponibles</span>
    </div>

    <div class="origen-export-card">
        <span class="dashicons dashicons-download" style="font-size: 48px; width: 48px; height: 48px; color: #00AA9F;"></span>
        <h2>Exportar a CSV</h2>
        <p>Se exportarán <strong><?php echo intval($total); ?> registros</strong> con todos los campos del formulario. El archivo CSV utiliza separador punto y coma (;) y codificación UTF-8.</p>

        <?php if (intval($total) > 0): ?>
            <a href="<?php echo esc_url($export_url); ?>" class="origen-export-btn">
                Descargar CSV
            </a>
        <?php else: ?>
            <p style="color: #999;"><em>No hay registros para exportar.</em></p>
        <?php endif; ?>
    </div>

</div>
