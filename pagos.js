class ProcesadorPagos {
    constructor() {
        this.apiUrl = 'procesar_pago.php';
    }

    async procesarPago(datosPago) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(datosPago)
            });

            const resultado = await response.json();
            
            if (resultado.success) {
                // Pago exitoso
                this.mostrarComprobante(resultado.venta_id, resultado.total);
                this.limpiarCarrito();
                return true;
            } else {
                throw new Error(resultado.message);
            }
        } catch (error) {
            console.error('Error en el pago:', error);
            this.mostrarError(error.message);
            return false;
        }
    }

    mostrarComprobante(ventaId, total) {
        const comprobanteHTML = `
            <div class="comprobante-pago">
                <div class="comprobante-header">
                    <h3><i class="bi bi-check-circle-fill text-success"></i> Pago Exitoso</h3>
                </div>
                <div class="comprobante-body">
                    <div class="comprobante-info">
                        <p><strong>Número de Venta:</strong> #${ventaId}</p>
                        <p><strong>Total Pagado:</strong> $${parseFloat(total).toFixed(2)}</p>
                        <p><strong>Fecha:</strong> ${new Date().toLocaleString()}</p>
                        <p><strong>Estado:</strong> <span class="text-success">Completado</span></p>
                    </div>
                    <div class="comprobante-actions">
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="bi bi-printer"></i> Imprimir
                        </button>
                        <button onclick="window.location.href='index.html'" class="btn btn-primary">
                            <i class="bi bi-house"></i> Volver al Inicio
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Reemplazar el contenido del checkout con el comprobante
        document.querySelector('.checkout-container').innerHTML = comprobanteHTML;
    }

    mostrarError(mensaje) {
        alert(`Error en el pago: ${mensaje}`);
    }

    limpiarCarrito() {
        localStorage.removeItem('carrito');
        if (typeof carrito !== 'undefined') {
            carrito.actualizarContadorCarrito();
        }
    }
}

// Instancia global del procesador de pagos
const procesadorPagos = new ProcesadorPagos();