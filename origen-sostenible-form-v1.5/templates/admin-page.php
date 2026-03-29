<?php
/**
 * Admin Page: Lista y detalle de formularios
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'origen_form_submissions';

// Ver detalle de un registro
$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
$view_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'view' && $view_id > 0) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $view_id), ARRAY_A);

    if (!$row) {
        echo '<div class="wrap origen-admin-wrap"><p>Registro no encontrado.</p></div>';
        return;
    }
    ?>
    <div class="wrap origen-admin-wrap">
        <a href="<?php echo esc_url(admin_url('admin.php?page=origen-form')); ?>" class="origen-back-link">
            <span class="dashicons dashicons-arrow-left-alt2"></span> Volver al listado
        </a>

        <div class="origen-admin-header">
            <h1>Solicitud #<?php echo intval($row['id']); ?></h1>
            <span class="badge"><?php echo esc_html($row['submitted_at']); ?></span>
        </div>

        <!-- Datos de contacto -->
        <div class="origen-detail-card">
            <h2>Datos de contacto</h2>
            <table class="origen-detail-table">
                <tr><td>Nombre:</td><td><?php echo esc_html($row['name']); ?></td></tr>
                <tr><td>Email:</td><td><a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a></td></tr>
                <tr><td>Teléfono:</td><td><a href="tel:<?php echo esc_attr($row['phone']); ?>"><?php echo esc_html($row['phone']); ?></a></td></tr>
                <tr><td>Privacidad aceptada:</td><td><?php echo esc_html($row['privacy_accepted']); ?></td></tr>
                <tr><td>Info comercial:</td><td><?php echo esc_html($row['commercial_accepted'] ?: 'no'); ?></td></tr>
            </table>
        </div>

        <!-- Datos del formulario -->
        <div class="origen-detail-card">
            <h2>Datos del formulario</h2>
            <table class="origen-detail-table">
                <tr><td>Tipo de propiedad:</td><td><?php echo esc_html(origen_get_label('property_type', $row['property_type'])); ?></td></tr>
                <tr><td>Tipo consumo:</td><td><?php echo esc_html($row['consumption_type']); ?></td></tr>
                <tr><td>Valor consumo:</td><td><?php echo esc_html(origen_get_label('consumption', $row['consumption_value'])); ?></td></tr>
                <tr><td>Servicios:</td><td><?php echo esc_html($row['services']); ?></td></tr>
                <tr><td>Plazo:</td><td><?php echo esc_html(origen_get_label('timeframe', $row['timeframe'])); ?></td></tr>
                <tr><td>Ubicación:</td><td><?php echo esc_html($row['location']); ?></td></tr>
                <tr><td>Info adicional:</td><td><?php echo esc_html($row['additional_info'] ?: 'N/A'); ?></td></tr>
                <tr><td>Quiere presupuesto:</td><td>
                    <?php if ($row['wants_quote'] === 'si'): ?>
                        <span style="color: #00AA9F; font-weight: 600;">Sí</span>
                    <?php else: ?>
                        <span style="color: #F39322;">No, prefiere contacto</span>
                    <?php endif; ?>
                </td></tr>
            </table>
        </div>

        <?php if ($row['wants_quote'] === 'si'): ?>
        <!-- Presupuesto -->
        <div class="origen-detail-card">
            <h2 class="budget-header">Presupuesto Calculado</h2>
            <table class="origen-detail-table">
                <tr><td>Tipo de techo:</td><td><?php echo esc_html(origen_get_label('roof_type', $row['roof_type'])); ?></td></tr>
                <tr><td>Orientación:</td><td><?php echo esc_html(origen_get_label('roof_orientation', $row['roof_orientation'])); ?></td></tr>
                <tr><td>Superficie:</td><td><?php echo esc_html(origen_get_label('roof_surface', $row['roof_surface'])); ?></td></tr>
                <tr><td>Cap. financiera:</td><td><?php echo esc_html(origen_get_label('financial_capacity', $row['financial_capacity'])); ?></td></tr>
                <tr><td>Toma de decisión:</td><td><?php echo esc_html(origen_get_label('decision_making', $row['decision_making'])); ?></td></tr>
                <tr><td>Motivación:</td><td><?php echo esc_html(origen_get_label('motivation', $row['motivation'])); ?></td></tr>
                <tr><td>Baterías:</td><td><?php echo esc_html(origen_get_label('battery_option', $row['battery_option'])); ?></td></tr>
                <tr><td>Cargador V.E.:</td><td><?php echo $row['wants_ve_charger'] === 'si' ? 'Sí' : 'No'; ?></td></tr>
                <tr><td>Potencia recomendada:</td><td><strong><?php echo esc_html($row['recommended_power']); ?> kW</strong></td></tr>
                <tr><td>Placas recomendadas:</td><td><strong><?php echo intval($row['recommended_panels']); ?> placas</strong></td></tr>
                <tr><td>Precio base:</td><td><?php echo number_format(floatval($row['base_price']), 2, ',', '.'); ?>€</td></tr>
                <tr><td>Precio baterías:</td><td><?php echo number_format(floatval($row['battery_price']), 2, ',', '.'); ?>€</td></tr>
                <tr><td>Precio cargador V.E.:</td><td><?php echo number_format(floatval($row['ve_price']), 2, ',', '.'); ?>€</td></tr>
                <tr class="total-row"><td>PRECIO TOTAL:</td><td><?php echo number_format(floatval($row['total_price']), 2, ',', '.'); ?>€</td></tr>
                <tr><td>Ahorro anual:</td><td style="color: #00AA9F; font-weight: 600;"><?php echo number_format(floatval($row['annual_savings']), 2, ',', '.'); ?>€/año</td></tr>
                <tr><td>Amortización:</td><td><?php echo number_format(floatval($row['payback_years']), 1, ',', '.'); ?> años</td></tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- Info técnica -->
        <div class="origen-detail-card">
            <h2>Información técnica</h2>
            <table class="origen-detail-table">
                <tr><td>IP:</td><td><?php echo esc_html($row['ip_address']); ?></td></tr>
                <tr><td>User Agent:</td><td style="font-size: 12px; word-break: break-all;"><?php echo esc_html($row['user_agent']); ?></td></tr>
                <tr><td>Fecha envío:</td><td><?php echo esc_html($row['submitted_at']); ?></td></tr>
            </table>
        </div>
    </div>
    <?php
    return;
}

// =========================================================================
// LISTADO DE FORMULARIOS
// =========================================================================

// Filtros
$filter_quote = isset($_GET['filter_quote']) ? sanitize_text_field(wp_unslash($_GET['filter_quote'])) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

// Paginación
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Construir query
$where = 'WHERE 1=1';
$params = array();

if ($filter_quote === 'si' || $filter_quote === 'no') {
    $where .= ' AND wants_quote = %s';
    $params[] = $filter_quote;
}

if (!empty($filter_date_from)) {
    $where .= ' AND submitted_at >= %s';
    $params[] = $filter_date_from . ' 00:00:00';
}

if (!empty($filter_date_to)) {
    $where .= ' AND submitted_at <= %s';
    $params[] = $filter_date_to . ' 23:59:59';
}

// Contar total
$count_query = "SELECT COUNT(*) FROM $table_name $where";
if (!empty($params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $params));
} else {
    $total_items = $wpdb->get_var($count_query);
}

$total_pages = ceil($total_items / $per_page);

// Obtener resultados
$query = "SELECT * FROM $table_name $where ORDER BY id DESC LIMIT %d OFFSET %d";
$query_params = array_merge($params, array($per_page, $offset));
$results = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
?>

<div class="wrap origen-admin-wrap">

    <div class="origen-admin-header">
        <h1>Formularios de Evaluación Energética</h1>
        <span class="badge"><?php echo intval($total_items); ?> registros</span>
    </div>

    <!-- Filtros -->
    <form method="get" class="origen-filters">
        <input type="hidden" name="page" value="origen-form">

        <label>Presupuesto:</label>
        <select name="filter_quote">
            <option value="">Todos</option>
            <option value="si" <?php selected($filter_quote, 'si'); ?>>Sí pidió</option>
            <option value="no" <?php selected($filter_quote, 'no'); ?>>No pidió</option>
        </select>

        <label>Desde:</label>
        <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>">

        <label>Hasta:</label>
        <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>">

        <button type="submit" class="button">Filtrar</button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=origen-form')); ?>" class="button">Limpiar</a>
    </form>

    <!-- Zona de borrado masivo -->
    <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <h3 style="margin: 0 0 10px 0; color: #856404;">Zona peligrosa</h3>
        <p style="margin: 0 0 10px 0; color: #856404;">Esta accion eliminara TODOS los registros de formularios. No se puede deshacer.</p>
        <button type="button" id="deleteAllRecords" class="button" style="background: #dc3545; color: white; border: none; padding: 10px 20px; cursor: pointer;">
            Borrar TODOS los registros
        </button>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#deleteAllRecords').on('click', function() {
            if (!confirm('Estas SEGURO de que quieres borrar TODOS los registros?\n\nEsta accion NO se puede deshacer.')) {
                return;
            }
            if (!confirm('ULTIMA CONFIRMACION:\n\nRealmente quieres eliminar TODOS los formularios enviados?')) {
                return;
            }
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'origen_delete_all_submissions',
                    nonce: '<?php echo esc_js(wp_create_nonce('origen_delete_all')); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Se han eliminado todos los registros correctamente.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error de conexion. Por favor, intentalo de nuevo.');
                }
            });
        });
        $('.delete-single').on('click', function() {
            var id = $(this).data('id');
            if (!confirm('Estas seguro de que quieres eliminar el registro #' + id + '?')) {
                return;
            }
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'origen_delete_single_submission',
                    nonce: '<?php echo esc_js(wp_create_nonce('origen_delete_single')); ?>',
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        });
    });
    </script>

    <?php if (empty($results)): ?>
        <div class="origen-empty-state">
            <span class="dashicons dashicons-clipboard"></span>
            <p>No se han encontrado formularios.</p>
        </div>
    <?php else: ?>
        <table class="origen-admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Fecha</th>
                    <th>Presupuesto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td>#<?php echo intval($row['id']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <td><a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a></td>
                    <td><?php echo esc_html($row['phone']); ?></td>
                    <td><?php echo esc_html(gmdate('d/m/Y H:i', strtotime($row['submitted_at']))); ?></td>
                    <td>
                        <?php if ($row['wants_quote'] === 'si'): ?>
                            <span class="badge-yes">Sí</span>
                        <?php else: ?>
                            <span class="badge-no">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=origen-form&action=view&id=' . intval($row['id']))); ?>" class="action-link">Ver</a>
                        <button class="button button-small delete-single" data-id="<?php echo intval($row['id']); ?>" style="background: #dc3545; color: white; border: none; margin-left: 5px; cursor: pointer;">Eliminar</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <div class="origen-pagination">
            <?php
            $base_url = admin_url('admin.php?page=origen-form');
            if ($filter_quote) $base_url .= '&filter_quote=' . urlencode($filter_quote);
            if ($filter_date_from) $base_url .= '&date_from=' . urlencode($filter_date_from);
            if ($filter_date_to) $base_url .= '&date_to=' . urlencode($filter_date_to);

            for ($i = 1; $i <= $total_pages; $i++):
                if ($i === $current_page):
                    ?>
                    <span class="current"><?php echo intval($i); ?></span>
                    <?php
                else:
                    ?>
                    <a href="<?php echo esc_url($base_url . '&paged=' . intval($i)); ?>"><?php echo intval($i); ?></a>
                    <?php
                endif;
            endfor;
            ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
