<?php
/**
 * Admin Page: Configuración de Precios
 */

if (!defined('ABSPATH')) {
    exit;
}

// Mostrar mensajes de éxito/error
settings_errors('origen_prices');

// Cargar precios actuales
$installations = get_option('origen_installation_prices', array(
    array('power' => 2, 'panels' => 4, 'price' => 2820),
    array('power' => 3, 'panels' => 6, 'price' => 3815),
    array('power' => 4, 'panels' => 7, 'price' => 4475),
    array('power' => 5, 'panels' => 9, 'price' => 5385),
    array('power' => 6, 'panels' => 11, 'price' => 6395),
    array('power' => 8, 'panels' => 14, 'price' => 7585),
    array('power' => 10, 'panels' => 17, 'price' => 8870),
));

$batteries = get_option('origen_battery_prices', array(
    '5kwh'  => 1356.75,
    '10kwh' => 2451.15,
    '15kwh' => 3536.55,
));

$ve_price = get_option('origen_ve_charger_price', 995);

// Indexar instalaciones por potencia para acceso fácil
$inst_by_power = array();
foreach ($installations as $inst) {
    $key = str_replace('.', '_', (string) $inst['power']);
    $inst_by_power[$key] = $inst;
}
?>

<div class="wrap origen-admin-wrap">

    <div class="origen-admin-header">
        <h1>Configuración de Precios</h1>
        <span class="badge">v1.5.1</span>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('origen_save_prices'); ?>
        <input type="hidden" name="origen_save_prices" value="1">

        <!-- Instalaciones Solares -->
        <div class="origen-pricing-section">
            <h2>Instalaciones Solares</h2>

            <div class="origen-pricing-grid">
                <div class="grid-header">Potencia</div>
                <div class="grid-header">Placas</div>
                <div class="grid-header">Precio (&euro;)</div>

                <?php
                $powers = array(2, 3, 4, 5, 6, 8, 10);
                foreach ($powers as $power):
                    $key = str_replace('.', '_', (string) $power);
                    $panels = isset($inst_by_power[$key]) ? $inst_by_power[$key]['panels'] : 0;
                    $price = isset($inst_by_power[$key]) ? $inst_by_power[$key]['price'] : 0;
                ?>
                    <label><strong><?php echo intval($power); ?> kW</strong></label>
                    <input type="number" name="panels_<?php echo esc_attr($key); ?>" value="<?php echo intval($panels); ?>" min="1" step="1">
                    <input type="number" name="price_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($price); ?>" min="0" step="0.01">
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Baterías -->
        <div class="origen-pricing-section">
            <h2>Bater&iacute;as</h2>

            <div class="origen-pricing-single">
                <label>5 kWh:</label>
                <input type="number" name="battery_5kwh" value="<?php echo esc_attr($batteries['5kwh'] ?? 1356.75); ?>" min="0" step="0.01">
                <span>&euro;</span>
            </div>
            <div class="origen-pricing-single">
                <label>10 kWh:</label>
                <input type="number" name="battery_10kwh" value="<?php echo esc_attr($batteries['10kwh'] ?? 2451.15); ?>" min="0" step="0.01">
                <span>&euro;</span>
            </div>
            <div class="origen-pricing-single">
                <label>15 kWh:</label>
                <input type="number" name="battery_15kwh" value="<?php echo esc_attr($batteries['15kwh'] ?? 3536.55); ?>" min="0" step="0.01">
                <span>&euro;</span>
            </div>
        </div>

        <!-- Cargador VE -->
        <div class="origen-pricing-section">
            <h2>Cargador Veh&iacute;culo El&eacute;ctrico</h2>

            <div class="origen-pricing-single">
                <label>Precio:</label>
                <input type="number" name="ve_charger_price" value="<?php echo esc_attr($ve_price); ?>" min="0" step="0.01">
                <span>&euro;</span>
            </div>
        </div>

        <!-- Parámetros Técnicos -->
        <div class="origen-pricing-section">
            <h2>Par&aacute;metros T&eacute;cnicos</h2>
            <p style="color: #666; margin-top: -10px; margin-bottom: 20px;">Estos valores se usan en los c&aacute;lculos de presupuesto. Modif&iacute;calos seg&uacute;n tus especificaciones t&eacute;cnicas.</p>

            <div class="origen-pricing-single">
                <label>m&sup2; por placa:</label>
                <input type="number" name="origen_sqm_per_panel" value="<?php echo esc_attr(get_option('origen_sqm_per_panel', 2.65)); ?>" step="0.01" min="1" max="5" style="width: 100px;">
                <span>m&sup2;</span>
            </div>
            <div class="origen-pricing-single">
                <label>HSP (horas sol):</label>
                <input type="number" name="origen_hsp_hours" value="<?php echo esc_attr(get_option('origen_hsp_hours', 4.8)); ?>" step="0.1" min="1" max="8" style="width: 100px;">
                <span>horas/d&iacute;a</span>
            </div>
            <div class="origen-pricing-single">
                <label>Precio electricidad:</label>
                <input type="number" name="origen_electricity_price" value="<?php echo esc_attr(get_option('origen_electricity_price', 0.2)); ?>" step="0.01" min="0.01" max="1" style="width: 100px;">
                <span>&euro;/kWh</span>
            </div>
            <div class="origen-pricing-single">
                <label>Potencia/placa:</label>
                <input type="number" name="origen_watts_per_panel" value="<?php echo esc_attr(get_option('origen_watts_per_panel', 590)); ?>" step="10" min="100" max="1000" style="width: 100px;">
                <span>W</span>
            </div>
        </div>

        <p>
            <button type="submit" class="origen-save-btn">Guardar todos los cambios</button>
        </p>

        <p style="color: #999; font-size: 13px;">Los cambios se aplican inmediatamente a nuevos formularios.</p>
    </form>

</div>
