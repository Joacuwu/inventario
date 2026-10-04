$(function () {
    var $modal = $('#modalMovimiento');
    var $form = $('#formMovimiento');
    if (!$modal.length) {
        return;
    }
    var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
    var toast = bootstrap.Toast.getOrCreateInstance(document.getElementById('toastOk'));

    // Abre el modal con los datos del producto y el tipo de movimiento.
    $('.js-movimiento').on('click', function () {
        var $btn = $(this);
        var entrada = $btn.data('tipo') === 'entrada';
        $form[0].reset();
        $form.find('[name=producto_id]').val($btn.data('id'));
        $form.find('[name=tipo]').val($btn.data('tipo'));
        $('#modalMovimientoTitulo').text(entrada ? 'Registrar entrada' : 'Registrar salida');
        $('.js-mov-producto').text($btn.data('nombre'));
        $('.js-mov-guardar').toggleClass('btn-success', entrada).toggleClass('btn-danger', !entrada);
        $('.js-mov-error').addClass('d-none');
        modal.show();
    });

    $modal.on('shown.bs.modal', function () {
        $('#cantidad').trigger('focus');
    });

    $form.on('submit', function (ev) {
        ev.preventDefault();
        var $guardar = $('.js-mov-guardar').prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (r) {
            actualizarFila($form.find('[name=producto_id]').val(), r.stock, r.minimo);
            modal.hide();
            $('#toastOk .toast-body').text(r.mensaje);
            toast.show();
        }).fail(function (xhr) {
            var r = xhr.responseJSON || {};
            if (xhr.status === 401) {
                window.location = 'login.php';
                return;
            }
            $('.js-mov-error').text(r.error || 'No se pudo registrar el movimiento.').removeClass('d-none');
        }).always(function () {
            $guardar.prop('disabled', false);
        });
    });

    // Actualiza el número y el color del stock, y el total de unidades del tablero.
    function actualizarFila(id, stock, minimo) {
        var $badge = $('#producto-' + id + ' .js-stock');
        var anterior = parseInt($badge.text(), 10) || 0;
        $badge.text(stock)
            .removeClass('text-bg-danger text-bg-warning text-bg-light border')
            .addClass(stock === 0 ? 'text-bg-danger' : (stock <= minimo ? 'text-bg-warning' : 'text-bg-light border'));

        var $total = $('#totalUnidades');
        var total = parseInt($total.text().replace(/\./g, ''), 10) || 0;
        $total.text((total + stock - anterior).toLocaleString('es-AR'));
    }
});
