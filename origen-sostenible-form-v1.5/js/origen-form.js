/**
 * Origen Sostenible - Formulario Evaluación Energética v1.5.3
 * Frontend JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';

    var currentStep = 1;
    var skipToEnd = false;
    var formSubmitting = false;
    var veChargerPreselected = false;

    // ID único para prevenir envíos duplicados
    var submissionId = 'sub_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    $('#submissionUid').val(submissionId);

    // Parámetros técnicos editables desde admin
    var techParams = origenForm.tech_params || {};
    var HSP = parseFloat(techParams.hsp) || 4.8;
    var ELECTRICITY_PRICE = parseFloat(techParams.electricity_price) || 0.2;
    var SQM_PER_PANEL = parseFloat(techParams.sqm_per_panel) || 2.65;

    // Datos de precios desde WordPress
    var installations = origenForm.installations || [];
    var batteries = origenForm.batteries || {};
    var veChargerPrice = parseFloat(origenForm.ve_charger) || 995;

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    showStep(1);
    updateDynamicPrices();

    // =========================================================================
    // DETECCIÓN CARGADOR VE EN SERVICIOS
    // =========================================================================

    $(document).on('change', 'input[name="services[]"]', function() {
        var services = [];
        $('input[name="services[]"]:checked').each(function() {
            services.push($(this).val());
        });
        veChargerPreselected = (services.indexOf('punto_recarga') !== -1);
    });

    // =========================================================================
    // NAVEGACIÓN
    // =========================================================================

    $('#btnNext').on('click', function() {
        if (!validateStep(currentStep)) {
            return;
        }

        // Lógica especial paso 7: si no quiere presupuesto, enviar directamente
        if (currentStep === 7) {
            var wantsQuote = $('input[name="wants_quote"]:checked').val();
            if (wantsQuote === 'no') {
                skipToEnd = true;
                submitForm();
                return;
            }
        }

        // Al pasar del paso 14 (baterías): si VE preseleccionado en servicios, saltar paso 15
        if (currentStep === 14 && veChargerPreselected) {
            $('input[name="ve_charger"][value="si"]').prop('checked', true);
            currentStep = 16; // Saltar directo a preferencia de contacto
            showStep(currentStep);
            return;
        }

        // Al llegar al paso 17 (viene del paso 16), calcular presupuesto
        if (currentStep === 16) {
            calculateBudget();
        }

        // Límite máximo: paso 17
        if (currentStep >= 17) {
            return;
        }

        currentStep++;
        showStep(currentStep);
    });

    $('#btnPrev').on('click', function() {
        if (currentStep > 1) {
            // Si está en paso 16 y se saltó paso 15 (VE preseleccionado), volver a paso 14
            if (currentStep === 16 && veChargerPreselected) {
                currentStep = 14;
                showStep(currentStep);
                return;
            }
            currentStep--;
            showStep(currentStep);
        }
    });

    $('#btnSubmit').on('click', function(e) {
        e.preventDefault();
        submitForm();
    });

    // =========================================================================
    // AUTO-AVANCE al seleccionar radio button
    // =========================================================================

    $(document).on('change', '.origen-form-step.active input[type="radio"]', function() {
        var step = currentStep;
        var selectedValue = $(this).val();

        // Pasos que NO auto-avanzan (inputs de texto, múltiples secciones)
        var noAutoAdvanceSteps = [5, 6];

        // No auto-avanzar si seleccionó "Otro" (requiere escribir valor)
        var isOtroSelected = selectedValue.indexOf('otro_') === 0;

        // Paso 7 "No": no auto-avanzar, mostrar botón enviar
        if (step === 7 && selectedValue === 'no') {
            $('#btnNext').hide();
            $('#btnSubmit').show();
            return;
        }

        // Paso 7 "Sí": restaurar botón siguiente
        if (step === 7 && selectedValue === 'si') {
            $('#btnSubmit').hide();
            $('#btnNext').show();
        }

        if (noAutoAdvanceSteps.indexOf(step) === -1 && !isOtroSelected) {
            setTimeout(function() {
                $('#btnNext').click();
            }, 300);
        }
    });

    // =========================================================================
    // MOSTRAR/OCULTAR INPUTS "OTRO"
    // =========================================================================

    // Consumo kWh: mostrar input cuando selecciona "Otro"
    $(document).on('change', 'input[name="consumption"]', function() {
        var val = $(this).val();
        if (val === 'otro_kwh') {
            $('#otroConsumoKwh').slideDown();
            $('#otroConsumoEuros').slideUp();
        } else if (val === 'otro_euros') {
            $('#otroConsumoEuros').slideDown();
            $('#otroConsumoKwh').slideUp();
        } else {
            $('#otroConsumoKwh').slideUp();
            $('#otroConsumoEuros').slideUp();
        }
    });

    // Superficie: mostrar input cuando selecciona "Otro"
    $(document).on('change', 'input[name="roof_surface"]', function() {
        var val = $(this).val();
        if (val === 'otro_superficie') {
            $('#otroSuperficie').slideDown();
        } else {
            $('#otroSuperficie').slideUp();
        }
    });

    // =========================================================================
    // MOSTRAR PASO
    // =========================================================================

    function showStep(step) {
        $('.origen-form-step').removeClass('active');
        $('.origen-form-step[data-step="' + step + '"]').addClass('active');
        updateProgress();
        updateButtons();

        // Scroll al inicio del formulario con offset para header fijo
        $('html, body').animate({
            scrollTop: $('#origenFormWrapper').offset().top - 100
        }, 300);
    }

    function updateProgress() {
        var displayStep = currentStep;
        var displayTotal = 7;

        if (currentStep <= 7) {
            displayStep = currentStep;
            displayTotal = 7;
        } else {
            displayTotal = 17;
        }

        var percent = (displayStep / displayTotal) * 100;
        $('#progressBar').css('width', Math.min(percent, 100) + '%');
        $('#stepIndicator').text(displayStep + ' de ' + displayTotal);
    }

    function updateButtons() {
        // Anterior
        if (currentStep <= 1) {
            $('#btnPrev').hide();
        } else {
            $('#btnPrev').show();
        }

        // Siguiente vs Enviar
        if (currentStep === 17) {
            // Paso 17 (presupuesto) = último paso, mostrar Enviar
            $('#btnNext').hide();
            $('#btnSubmit').show();
        } else if (currentStep === 7) {
            // Paso 7: depende de la selección
            var wantsQuote = $('input[name="wants_quote"]:checked').val();
            if (wantsQuote === 'no') {
                $('#btnNext').hide();
                $('#btnSubmit').show();
            } else {
                $('#btnNext').show();
                $('#btnSubmit').hide();
            }
        } else {
            $('#btnNext').show();
            $('#btnSubmit').hide();
        }
    }

    // =========================================================================
    // TOGGLE kWh / €
    // =========================================================================

    $('.consumption-toggle').on('click', function() {
        var mode = $(this).data('mode');

        // Actualizar botones toggle
        $('.consumption-toggle').removeClass('active');
        $(this).addClass('active');

        // Actualizar hidden input
        $('#consumptionType').val(mode);

        // Desmarcar todas las opciones de consumo
        $('.consumption-options input[type="radio"]').prop('checked', false);

        // Mostrar/ocultar opciones
        $('.consumption-options').hide();
        $('.consumption-options[data-type="' + mode + '"]').show();

        // Ocultar inputs "Otro"
        $('#otroConsumoKwh').slideUp();
        $('#otroConsumoEuros').slideUp();
    });

    // =========================================================================
    // SELECCIÓN VISUAL (click en tarjetas)
    // =========================================================================

    $(document).on('click', '.origen-option-card', function() {
        var input = $(this).find('input');

        if (input.attr('type') === 'radio') {
            // Para radio: deseleccionar hermanos visibles
            var name = input.attr('name');
            $(this).closest('.origen-options-grid').find('input[name="' + name + '"]')
                .closest('.origen-option-card').removeClass('selected');
            $(this).addClass('selected');
            input.prop('checked', true).trigger('change');
        } else if (input.attr('type') === 'checkbox') {
            // Para checkbox: toggle
            var isChecked = input.prop('checked');
            input.prop('checked', !isChecked).trigger('change');
            $(this).toggleClass('selected', !isChecked);
        }
    });

    // =========================================================================
    // VALIDACIÓN
    // =========================================================================

    function validateStep(step) {
        var $step = $('.origen-form-step[data-step="' + step + '"]');
        var isValid = true;

        // Limpiar errores previos
        $step.find('.origen-field-error').removeClass('origen-field-error');
        $step.find('.origen-error-text').remove();

        switch(step) {
            case 1: // Tipo de propiedad
                if (!$('input[name="property_type"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona un tipo de propiedad.');
                    isValid = false;
                }
                break;

            case 2: // Consumo
                var activeType = $('#consumptionType').val();
                var checkedConsumption = $('.consumption-options[data-type="' + activeType + '"] input[type="radio"]:checked');
                if (!checkedConsumption.length) {
                    showStepError($step, 'Por favor, selecciona tu consumo aproximado.');
                    isValid = false;
                } else {
                    var consumptionVal = checkedConsumption.val();
                    if (consumptionVal === 'otro_kwh') {
                        var otroKwh = $('input[name="consumption_other_kwh"]').val();
                        if (!otroKwh || parseFloat(otroKwh) <= 0) {
                            $('input[name="consumption_other_kwh"]').addClass('origen-field-error');
                            showStepError($step, 'Por favor, indica tu consumo en kWh.');
                            isValid = false;
                        }
                    } else if (consumptionVal === 'otro_euros') {
                        var otroEuros = $('input[name="consumption_other_euros"]').val();
                        if (!otroEuros || parseFloat(otroEuros) <= 0) {
                            $('input[name="consumption_other_euros"]').addClass('origen-field-error');
                            showStepError($step, 'Por favor, indica tu factura en \u20AC/mes.');
                            isValid = false;
                        }
                    }
                }
                break;

            case 3: // Servicios (al menos uno)
                if (!$('input[name="services[]"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona al menos un servicio.');
                    isValid = false;
                }
                break;

            case 4: // Plazo
                if (!$('input[name="timeframe"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona un plazo.');
                    isValid = false;
                }
                break;

            case 5: // Ubicación
                if (!$('#location').val().trim()) {
                    $('#location').addClass('origen-field-error');
                    showFieldError($('#location'), 'Este campo es obligatorio.');
                    isValid = false;
                }
                break;

            case 6: // Datos de contacto
                var name = $('#name').val().trim();
                var email = $('#email').val().trim();
                var phone = $('#phone').val().trim();
                var privacy = $('input[name="privacy_accepted"]').is(':checked');

                if (!name) {
                    $('#name').addClass('origen-field-error');
                    showFieldError($('#name'), 'El nombre es obligatorio.');
                    isValid = false;
                }
                if (!email || !isValidEmail(email)) {
                    $('#email').addClass('origen-field-error');
                    showFieldError($('#email'), 'Introduce un email v\u00E1lido.');
                    isValid = false;
                }
                if (!phone) {
                    $('#phone').addClass('origen-field-error');
                    showFieldError($('#phone'), 'El tel\u00E9fono es obligatorio.');
                    isValid = false;
                }
                if (!privacy) {
                    showStepError($step, 'Debes aceptar la pol\u00EDtica de privacidad.');
                    isValid = false;
                }
                break;

            case 7: // Presupuesto
                if (!$('input[name="wants_quote"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opci\u00F3n.');
                    isValid = false;
                }
                break;

            case 8:
                if (!$('input[name="roof_type"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona un tipo de techo.');
                    isValid = false;
                }
                break;

            case 9:
                if (!$('input[name="roof_orientation"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una orientaci\u00F3n.');
                    isValid = false;
                }
                break;

            case 10: // Superficie
                var surfaceChecked = $('input[name="roof_surface"]:checked');
                if (!surfaceChecked.length) {
                    showStepError($step, 'Por favor, selecciona una superficie.');
                    isValid = false;
                } else if (surfaceChecked.val() === 'otro_superficie') {
                    var otroSup = $('input[name="roof_surface_other"]').val();
                    if (!otroSup || parseFloat(otroSup) <= 0) {
                        $('input[name="roof_surface_other"]').addClass('origen-field-error');
                        showStepError($step, 'Por favor, indica la superficie en m\u00B2.');
                        isValid = false;
                    }
                }
                break;

            case 11:
                if (!$('input[name="financial_capacity"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opci\u00F3n.');
                    isValid = false;
                }
                break;

            case 12:
                if (!$('input[name="decision_making"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opci\u00F3n.');
                    isValid = false;
                }
                break;

            case 13:
                if (!$('input[name="motivation"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una motivaci\u00F3n.');
                    isValid = false;
                }
                break;

            case 14: // Baterías
                if (!$('input[name="battery_option"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opci\u00F3n de bater\u00EDas.');
                    isValid = false;
                }
                break;

            case 15: // Cargador VE
                if (!$('input[name="ve_charger"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona una opci\u00F3n.');
                    isValid = false;
                }
                break;

            case 16: // Preferencia de contacto
                if (!$('input[name="contact_preference"]:checked').length) {
                    showStepError($step, 'Por favor, selecciona cu\u00E1ndo prefieres que te contactemos.');
                    isValid = false;
                }
                break;
        }

        return isValid;
    }

    function showStepError($step, message) {
        if (!$step.find('.origen-error-text').length) {
            $step.append('<p class="origen-error-text" style="text-align: center; margin-top: 10px;">' + message + '</p>');
        }
    }

    function showFieldError($field, message) {
        if (!$field.next('.origen-error-text').length) {
            $field.after('<p class="origen-error-text">' + message + '</p>');
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Limpiar errores al interactuar
    $(document).on('change', 'input, textarea, select', function() {
        $(this).removeClass('origen-field-error');
        $(this).next('.origen-error-text').remove();
        $(this).closest('.origen-form-step').find('> .origen-error-text').remove();
    });

    // =========================================================================
    // CÁLCULO DE PRESUPUESTO
    // =========================================================================

    function calculateBudget() {
        // Obtener valores
        var consumptionType = $('#consumptionType').val();
        var consumptionValue = $('input[name="consumption"]:checked').val();
        var roofSurface = $('input[name="roof_surface"]:checked').val();
        var batteryOption = $('input[name="battery_option"]:checked').val();
        var wantsVE = ($('input[name="ve_charger"]:checked').val() === 'si');

        // Criterio A o B: kW por consumo
        var kwConsumption = 0;
        if (consumptionType === 'euro') {
            if (consumptionValue === 'otro_euros') {
                var customEuros = parseFloat($('input[name="consumption_other_euros"]').val()) || 225;
                kwConsumption = kwByEurosCustom(customEuros);
            } else {
                kwConsumption = kwByEuros(consumptionValue);
            }
        } else {
            if (consumptionValue === 'otro_kwh') {
                var customKwh = parseFloat($('input[name="consumption_other_kwh"]').val()) || 350;
                kwConsumption = kwByKwhCustom(customKwh);
            } else {
                kwConsumption = kwByKwh(consumptionValue);
            }
        }

        // Criterio C: kW por superficie
        var kwSurface;
        if (roofSurface === 'otro_superficie') {
            var customSqm = parseFloat($('input[name="roof_surface_other"]').val()) || 35;
            kwSurface = kwBySurfaceCustom(customSqm);
        } else {
            kwSurface = kwBySurface(roofSurface);
        }

        // Selección final
        var kwFinal = kwConsumption;
        var adjustmentReason;
        if (kwFinal > kwSurface) {
            kwFinal = kwSurface;
            adjustmentReason = 'superficie';
        } else if (consumptionType === 'euro') {
            adjustmentReason = 'euros';
        } else {
            adjustmentReason = 'kwh';
        }

        // Buscar instalación adecuada
        var installation = findInstallation(kwFinal);

        // Precios
        var basePrice = installation.price;
        var batteryPrice = 0;
        if (batteryOption !== 'none' && batteries[batteryOption]) {
            batteryPrice = parseFloat(batteries[batteryOption]);
        }
        var vePrice = wantsVE ? veChargerPrice : 0;
        var totalPrice = basePrice + batteryPrice + vePrice;

        // Ahorro y amortización
        var kwhGeneratedMonth = installation.power * HSP * 30;
        var monthlySavings = kwhGeneratedMonth * ELECTRICITY_PRICE;
        var annualSavings = monthlySavings * 12;
        var paybackYears = annualSavings > 0 ? totalPrice / annualSavings : 0;

        // Mostrar resultados
        $('#recommendedPower').text(installation.power + ' kW');
        $('#recommendedPanels').text(installation.panels + ' placas');
        $('#basePrice').text(formatCurrency(basePrice));

        // Extras
        var extrasText = [];
        if (batteryPrice > 0) {
            extrasText.push('Bater\u00EDa: +' + formatCurrency(batteryPrice));
        }
        if (vePrice > 0) {
            extrasText.push('Cargador V.E.: +' + formatCurrency(vePrice));
        }
        if (extrasText.length > 0) {
            $('#extrasDetail').html(extrasText.join('<br>'));
            $('#extrasRow').show();
        } else {
            $('#extrasRow').hide();
        }

        $('#totalPrice').text(formatCurrency(totalPrice));
        $('#annualSavings').text(formatCurrency(annualSavings) + '/a\u00F1o');
        $('#paybackYears').text(paybackYears.toFixed(1) + ' a\u00F1os');

        // Disclaimer dinámico
        var disclaimerTexts = {
            'kwh': 'Esta es una propuesta aproximada y ficticia ajustada a su consumo en kWh',
            'euros': 'Esta es una propuesta aproximada y ficticia ajustada a su consumo en \u20AC/mes',
            'superficie': 'Esta es una propuesta aproximada y ficticia ajustada a su superficie disponible'
        };
        var disclaimerBase = disclaimerTexts[adjustmentReason] || 'Esta es una propuesta aproximada y ficticia';
        $('#disclaimerText').text(disclaimerBase + ', sujeta a contacto directo, evaluaci\u00F3n de necesidades reales y visita t\u00E9cnica para presupuesto definitivo.');

        // Guardar en campos ocultos
        $('#calcRecommendedPower').val(installation.power);
        $('#calcRecommendedPanels').val(installation.panels);
        $('#calcBasePrice').val(basePrice.toFixed(2));
        $('#calcBatteryPrice').val(batteryPrice.toFixed(2));
        $('#calcVePrice').val(vePrice.toFixed(2));
        $('#calcTotalPrice').val(totalPrice.toFixed(2));
        $('#calcAnnualSavings').val(annualSavings.toFixed(2));
        $('#calcPaybackYears').val(paybackYears.toFixed(1));
        $('#calcAdjustmentReason').val(adjustmentReason);
    }

    // Funciones de cálculo con valores predefinidos
    function kwByKwh(value) {
        var averages = {
            'menos_200': 150,
            '200_500': 350,
            'mas_500': 650
        };
        var kwhMes = averages[value] || 350;
        return kwhMes / 30 / HSP;
    }

    function kwByEuros(value) {
        var averages = {
            '50_150': 100,
            '150_300': 225,
            'mas_300': 400
        };
        var euroMes = averages[value] || 225;
        return euroMes / 30 / ELECTRICITY_PRICE / HSP;
    }

    // Funciones con valores personalizados
    function kwByKwhCustom(kwhMes) {
        return kwhMes / 30 / HSP;
    }

    function kwByEurosCustom(euroMes) {
        return euroMes / 30 / ELECTRICITY_PRICE / HSP;
    }

    function kwBySurface(value) {
        var surfaces = {
            'menos_20': 15,
            '20_50': 35,
            'mas_50': 65,
            'no_seguro': 35
        };
        var sqm = surfaces[value] || 35;
        return kwBySurfaceCustom(sqm);
    }

    function kwBySurfaceCustom(sqm) {
        var panelsPossible = Math.floor(sqm / SQM_PER_PANEL);

        var kwSurface = 0;
        for (var i = 0; i < installations.length; i++) {
            if (installations[i].panels <= panelsPossible) {
                kwSurface = installations[i].power;
            }
        }

        if (kwSurface === 0 && installations.length > 0) {
            kwSurface = installations[0].power;
        }

        return kwSurface;
    }

    function findInstallation(kwNeeded) {
        var selected = installations[installations.length - 1]; // Default: la más grande

        for (var i = 0; i < installations.length; i++) {
            if (installations[i].power >= kwNeeded) {
                selected = installations[i];
                break;
            }
        }

        return selected;
    }

    function formatCurrency(value) {
        return value.toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '\u20AC';
    }

    // =========================================================================
    // PRECIOS DINÁMICOS EN EL FORMULARIO
    // =========================================================================

    function updateDynamicPrices() {
        if (batteries['5kwh']) {
            $('.battery-price-5kwh').text(parseFloat(batteries['5kwh']).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
        if (batteries['10kwh']) {
            $('.battery-price-10kwh').text(parseFloat(batteries['10kwh']).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
        if (batteries['15kwh']) {
            $('.battery-price-15kwh').text(parseFloat(batteries['15kwh']).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
        $('.ve-charger-price').text(veChargerPrice.toLocaleString('es-ES', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
    }

    // =========================================================================
    // ENVÍO FORMULARIO
    // =========================================================================

    function submitForm() {
        // Prevenir envío duplicado
        if (formSubmitting) return;
        formSubmitting = true;

        var $form = $('#origenEvaluationForm');

        // Mostrar spinner
        $('#navButtons').hide();
        $('#origenSpinner').show();

        var formData = new FormData($form[0]);
        formData.append('action', 'origen_submit_form');
        formData.append('nonce', origenForm.nonce);

        $.ajax({
            url: origenForm.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#origenSpinner').hide();
                if (response.success) {
                    // Mostrar éxito
                    $('.origen-form-step').removeClass('active');
                    $('.origen-progress-container').hide();
                    $('#successMessage').show();
                    $('#navButtons').hide();

                    $('html, body').animate({
                        scrollTop: $('#origenFormWrapper').offset().top - 100
                    }, 300);
                } else {
                    formSubmitting = false;
                    alert(response.data.message || 'Error al enviar el formulario.');
                    $('#navButtons').show();
                }
            },
            error: function() {
                formSubmitting = false;
                $('#origenSpinner').hide();
                alert('Error de conexi\u00F3n. Por favor, int\u00E9ntalo de nuevo.');
                $('#navButtons').show();
            }
        });
    }

});
