// carrito.js - Funciones del carrito de compras
class Carrito {
    constructor() {
        this.items = this.obtenerCarritoLocalStorage();
    }

    // Obtener carrito desde localStorage
    obtenerCarritoLocalStorage() {
        const carrito = localStorage.getItem('carrito');
        return carrito ? JSON.parse(carrito) : [];
    }

    // Guardar carrito en localStorage
    guardarCarrito() {
        localStorage.setItem('carrito', JSON.stringify(this.items));
    }

    // Agregar producto al carrito
    agregarProducto(producto, color, talla, cantidad = 1) {
        // Asegurar que los precios sean números
        const precio = parseFloat(producto.precio) || 0;
        const precioFinal = parseFloat(producto.precio_final) || precio;
        
        const itemExistente = this.items.find(item => 
            item.id === producto.id && 
            item.color === color && 
            item.talla === talla
        );

        if (itemExistente) {
            itemExistente.cantidad += cantidad;
        } else {
            this.items.push({
                id: producto.id,
                nombre: producto.nombre,
                precio: precio,
                precio_final: precioFinal,
                imagen: producto.imagen,
                color: color,
                talla: talla,
                cantidad: cantidad,
                categoria: producto.categoria
            });
        }

        this.guardarCarrito();
        this.actualizarContadorCarrito();
        this.mostrarNotificacion('Producto agregado al carrito');
    }

    // Eliminar producto del carrito
    eliminarProducto(index) {
        this.items.splice(index, 1);
        this.guardarCarrito();
        this.actualizarContadorCarrito();
    }

    // Actualizar cantidad de producto
    actualizarCantidad(index, nuevaCantidad) {
        if (nuevaCantidad > 0) {
            this.items[index].cantidad = nuevaCantidad;
            this.guardarCarrito();
            this.actualizarContadorCarrito();
        }
    }

    // Obtener total del carrito
    obtenerTotal() {
        return this.items.reduce((total, item) => {
            return total + (parseFloat(item.precio_final) * item.cantidad);
        }, 0);
    }

    // Obtener cantidad total de productos
    obtenerCantidadTotal() {
        return this.items.reduce((total, item) => total + item.cantidad, 0);
    }

    // Actualizar contador en el ícono del carrito
    actualizarContadorCarrito() {
        const contadores = document.querySelectorAll('.cart-count');
        const cantidadTotal = this.obtenerCantidadTotal();
        
        contadores.forEach(contador => {
            contador.textContent = cantidadTotal;
            if (cantidadTotal > 0) {
                contador.style.display = 'flex';
            } else {
                contador.style.display = 'none';
            }
        });
    }

    // Mostrar notificación
    mostrarNotificacion(mensaje) {
        // Crear notificación
        const notificacion = document.createElement('div');
        notificacion.className = 'cart-notification';
        notificacion.innerHTML = `
            <div class="notification-content">
                <i class="bi bi-check-circle"></i>
                <span>${mensaje}</span>
            </div>
        `;

        document.body.appendChild(notificacion);

        // Remover después de 3 segundos
        setTimeout(() => {
            notificacion.remove();
        }, 3000);
    }

    // Obtener información del usuario
    getUserInfo() {
        return {
            userName: localStorage.getItem('userName'),
            userEmail: localStorage.getItem('userEmail'),
            userId: localStorage.getItem('userId'),
            isLoggedIn: localStorage.getItem('isLoggedIn') === 'true'
        };
    }

    // Ir al checkout - MODIFICADA PARA ELIMINAR ALERTAS
    irAlCheckout() {
        if (this.items.length === 0) {
            this.mostrarNotificacion('El carrito está vacío');
            return;
        }

        // Verificar si el usuario está logueado
        const userInfo = this.getUserInfo();
        if (!userInfo.isLoggedIn) {
            // Redirigir directamente sin mostrar alerta
            window.location.href = 'login/iniciarsesion.html?redirect=checkout';
            return;
        }

        // Redirigir a la página de checkout SIN MOSTRAR ALERTAS
        window.location.href = 'checkout.html';
    }

    renderizarCarrito() {
        const carritoContainer = document.getElementById('cartSidebar');
        if (!carritoContainer) return;

        if (this.items.length === 0) {
            carritoContainer.innerHTML = `
                <div class="empty-cart">
                    <i class="bi bi-cart-x"></i>
                    <p>Tu carrito está vacío</p>
                    <a href="index.html" class="btn-continue-shopping">Continuar comprando</a>
                </div>
            `;
            return;
        }

        let html = `
            <div class="cart-header">
                <h3>Tu Carrito (${this.obtenerCantidadTotal()})</h3>
                <button class="close-cart"><i class="bi bi-x"></i></button>
            </div>
            <div class="cart-items">
        `;

        this.items.forEach((item, index) => {
            const precio = parseFloat(item.precio_final);
            const subtotal = precio * item.cantidad;
            
            html += `
                <div class="cart-item">
                    <div class="item-image">
                        <img src="${item.imagen}" alt="${item.nombre}" 
                             onerror="this.src='https://via.placeholder.com/80x100?text=Imagen'">
                    </div>
                    <div class="item-details">
                        <h4 class="item-title">${item.nombre}</h4>
                        <p class="item-variants">Color: ${item.color} | Talla: ${item.talla}</p>
                        <div class="item-controls">
                            <div class="quantity-controls">
                                <button class="btn-quantity minus" data-index="${index}">-</button>
                                <span class="quantity">${item.cantidad}</span>
                                <button class="btn-quantity plus" data-index="${index}">+</button>
                            </div>
                            <div class="item-price">
                                $${precio.toFixed(2)} x ${item.cantidad} = $${subtotal.toFixed(2)}
                            </div>
                        </div>
                    </div>
                    <button class="btn-remove" data-index="${index}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
        });

        html += `</div>`;

        const total = this.obtenerTotal();
        html += `
            <div class="cart-footer">
                <div class="cart-total">
                    <span>Total:</span>
                    <span class="total-amount">$${total.toFixed(2)}</span>
                </div>
                <button class="btn-checkout">
                    <i class="bi bi-credit-card"></i>
                    PROCEDER AL PAGO
                </button>
                <a href="index.html" class="btn-continue-shopping">Continuar comprando</a>
            </div>
        `;

        carritoContainer.innerHTML = html;

        // Agregar event listeners
        this.agregarEventListenersCarrito();
    }

    // Agregar event listeners a los controles del carrito
    agregarEventListenersCarrito() {
        // Botones de cantidad
        document.querySelectorAll('.btn-quantity.minus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('.btn-quantity').dataset.index);
                const nuevaCantidad = this.items[index].cantidad - 1;
                this.actualizarCantidad(index, nuevaCantidad);
                this.renderizarCarrito();
            });
        });

        document.querySelectorAll('.btn-quantity.plus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('.btn-quantity').dataset.index);
                const nuevaCantidad = this.items[index].cantidad + 1;
                this.actualizarCantidad(index, nuevaCantidad);
                this.renderizarCarrito();
            });
        });

        // Botones de eliminar
        document.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('.btn-remove').dataset.index);
                this.eliminarProducto(index);
                this.renderizarCarrito();
            });
        });

        // Cerrar carrito
        document.querySelector('.close-cart')?.addEventListener('click', () => {
            this.ocultarCarrito();
        });

        // Proceder al pago - MODIFICADO PARA PREVENIR ALERTAS
        document.querySelector('.btn-checkout')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.irAlCheckout();
        });
    }

    // Mostrar carrito
    mostrarCarrito() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('cartOverlay');
        
        if (sidebar && overlay) {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    // Ocultar carrito
    ocultarCarrito() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('cartOverlay');
        
        if (sidebar && overlay) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
}

// Instancia global del carrito
const carrito = new Carrito();