-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-11-2025 a las 17:03:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tienda_ropa`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades`
--

CREATE TABLE `actividades` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('venta','inventario','cliente','devolucion','pedido','alerta','sistema') DEFAULT 'sistema',
  `referencia_id` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `actividades`
--

INSERT INTO `actividades` (`id`, `usuario_id`, `accion`, `descripcion`, `tipo`, `referencia_id`, `fecha_registro`) VALUES
(1, 1, 'Sistema iniciado', 'El sistema ha sido iniciado correctamente', 'sistema', NULL, '2025-11-18 01:44:23'),
(2, 1, 'Usuario registrado', 'Usuario administrador creado', 'sistema', NULL, '2025-11-18 01:44:23'),
(3, 1, 'Usuario creado', 'Nuevo usuario registrado: kevin', 'sistema', NULL, '2025-11-18 01:46:37'),
(4, 1, 'Producto creado', 'Nuevo producto agregado: kevin', 'inventario', NULL, '2025-11-18 02:04:12'),
(5, 1, 'Producto eliminado', 'Producto eliminado: kevin', 'inventario', NULL, '2025-11-18 02:04:33'),
(6, 1, 'Producto creado', 'Nuevo producto agregado: kevin1', 'inventario', NULL, '2025-11-18 02:24:36'),
(7, 1, 'Producto actualizado', 'Producto actualizado: kevin1', 'inventario', NULL, '2025-11-18 02:40:12'),
(8, 1, 'Producto creado', 'Nuevo producto agregado: camisas', 'inventario', NULL, '2025-11-21 21:34:02'),
(9, 1, 'Producto eliminado', 'Producto eliminado: kevin1', 'inventario', NULL, '2025-11-21 21:34:20'),
(10, 1, 'Producto actualizado', 'Producto actualizado: camisas', 'inventario', NULL, '2025-11-21 21:34:35'),
(11, 1, 'Producto creado', 'Nuevo producto agregado: kevinasd', 'inventario', NULL, '2025-11-21 22:04:32'),
(12, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 22:10:57'),
(13, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 22:11:15'),
(14, 1, 'Producto actualizado', 'Producto actualizado: camisas', 'inventario', NULL, '2025-11-21 22:11:23'),
(15, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 22:16:20'),
(16, 1, 'Producto eliminado', 'Producto eliminado: camisas', 'inventario', NULL, '2025-11-21 22:54:07'),
(17, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 22:56:12'),
(18, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 23:04:19'),
(19, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 23:28:49'),
(20, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-21 23:28:56'),
(21, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-22 00:14:45'),
(22, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-22 00:17:27'),
(23, 1, 'Usuario actualizado', 'Usuario actualizado: kevinalexander', 'sistema', NULL, '2025-11-22 00:35:03'),
(24, 1, 'Usuario creado', 'Nuevo usuario registrado: hola', 'sistema', NULL, '2025-11-22 00:35:29'),
(25, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-22 00:38:48'),
(26, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-22 00:39:13'),
(27, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-22 00:50:50'),
(28, 1, 'Sucursal creada', 'Nueva sucursal: ads', 'sistema', NULL, '2025-11-22 12:54:33'),
(29, 1, 'Sucursal eliminada', 'Sucursal eliminada: ads', 'sistema', NULL, '2025-11-22 12:54:57'),
(30, 1, 'Sucursal actualizada', 'Sucursal actualizada: Sucursal Centro', 'sistema', NULL, '2025-11-22 13:40:33'),
(31, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-22 14:48:31'),
(32, 1, 'Usuario actualizado', 'Usuario actualizado: hola1', 'sistema', NULL, '2025-11-22 14:48:41'),
(33, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-23 04:24:08'),
(34, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-23 04:31:13'),
(35, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, '2025-11-23 04:37:08'),
(36, 2, 'Producto actualizado', 'Producto actualizado: camisa azul', 'inventario', NULL, '2025-11-23 04:42:47'),
(37, 2, 'Producto creado', 'Nuevo producto agregado: zapatos', 'inventario', NULL, '2025-11-23 04:44:02'),
(38, 1, 'Producto creado', 'Nuevo producto agregado: Vestido Negro Noche', 'inventario', NULL, '2025-11-23 04:48:09'),
(39, 1, 'Producto creado', 'Nuevo producto agregado: Bufanda de Cachemira', 'inventario', NULL, '2025-11-23 04:50:31'),
(40, 2, 'Venta registrada', 'Venta #8 procesada exitosamente. Total: $178895.2', 'venta', 8, '2025-11-23 05:33:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_activos`
--

CREATE TABLE `clientes_activos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes_activos`
--

INSERT INTO `clientes_activos` (`id`, `nombre`, `correo`, `telefono`, `estado`, `fecha_registro`) VALUES
(1, 'Juan Pérez', 'juan@email.com', '555-2001', 'activo', '2025-11-18 01:44:23'),
(2, 'María García', 'maria@email.com', '555-2002', 'activo', '2025-11-18 01:44:23'),
(3, 'Carlos López', 'carlos@email.com', '555-2003', 'activo', '2025-11-18 01:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedidos`
--

CREATE TABLE `detalle_pedidos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(21, 8, 9, 1, 21000.00, 21000.00),
(22, 8, 10, 1, 18720.00, 18720.00),
(23, 8, 11, 1, 39500.00, 39500.00),
(24, 8, 12, 1, 75000.00, 75000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','procesado','completado','cancelado') DEFAULT 'pendiente',
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_stock`
--

CREATE TABLE `productos_stock` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `porcentaje_descuento` int(11) DEFAULT 0,
  `precio_final` decimal(10,2) GENERATED ALWAYS AS (`precio` - `descuento`) STORED,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `categoria` varchar(100) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_stock`
--

INSERT INTO `productos_stock` (`id`, `nombre`, `descripcion`, `precio`, `descuento`, `porcentaje_descuento`, `cantidad`, `categoria`, `sucursal_id`, `estado`, `imagen`, `fecha_creacion`) VALUES
(9, 'camisa azul', 'camisa para caballero', 30000.00, 9000.00, 30, 44, 'camisas', 2, 'activo', '692290c78fb86_1763872967.jpg', '2025-11-21 22:04:32'),
(10, 'zapatos', 'zapatos clásicos', 24000.00, 5280.00, 22, 39, 'camisas', 2, 'activo', '6922911285808_1763873042.webp', '2025-11-23 04:44:02'),
(11, 'Vestido Negro Noche', 'vestido para dama elegante', 50000.00, 10500.00, 21, 39, 'vestidos', 1, 'activo', '69229209157d9_1763873289.webp', '2025-11-23 04:48:09'),
(12, 'Bufanda de Cachemira', 'bufanda para dama', 75000.00, 0.00, 0, 19, 'accesorios', 1, 'activo', '69229297c35c4_1763873431.webp', '2025-11-23 04:50:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `fecha_creacion`) VALUES
(1, 'administrador', 'Acceso completo al sistema', '2025-11-18 01:44:23'),
(2, 'vendedor', 'Puede gestionar ventas y clientes', '2025-11-18 01:44:23'),
(3, 'cajero', 'Puede procesar pagos y transacciones', '2025-11-18 01:44:23'),
(4, 'almacen', 'Gestiona inventario y productos', '2025-11-18 01:44:23'),
(5, 'diseñador', 'Gestiona contenido y diseño', '2025-11-18 01:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `encargado` varchar(100) DEFAULT NULL,
  `estado` enum('activa','inactiva') DEFAULT 'activa',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sucursales`
--

INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `telefono`, `encargado`, `estado`, `fecha_creacion`) VALUES
(1, 'Sucursal Principal', 'Av. Principal #123, la playa', '555-1001', NULL, 'activa', '2025-11-18 01:44:23'),
(2, 'Sucursal Centro', 'Calle Norte #456, Zona Norte', '555-1002', 'kevin', 'activa', '2025-11-18 01:44:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `correo`, `password`, `rol_id`, `fecha_registro`, `estado`) VALUES
(1, 'Administrador Principal', 'admin@tiendaropa.com', 'admin123', 1, '2025-11-18 01:44:23', 'activo'),
(2, 'kevinalexander', 'hola@gmail.com', '123456789', 3, '2025-11-18 01:46:37', 'activo'),
(3, 'hola1', 'kevin1@gmail.com', '$2y$10$EOrh1mEym9divTLpuUvnpO.XG/WN9eGFVCvfJiP3MzS.MtvM0eoYa', 4, '2025-11-22 00:35:29', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `impuesto` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','tarjeta','qr') DEFAULT 'efectivo',
  `efectivo_recibido` decimal(10,2) DEFAULT NULL,
  `cambio` decimal(10,2) DEFAULT NULL,
  `estado` enum('completada','cancelada') DEFAULT 'completada',
  `fecha_venta` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `cliente_id`, `pedido_id`, `sucursal_id`, `usuario_id`, `subtotal`, `impuesto`, `total`, `metodo_pago`, `efectivo_recibido`, `cambio`, `estado`, `fecha_venta`) VALUES
(8, NULL, NULL, 1, 2, 154220.00, 24675.20, 178895.20, 'efectivo', 200000.00, 21104.80, 'completada', '2025-11-23 05:33:42');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `clientes_activos`
--
ALTER TABLE `clientes_activos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `productos_stock`
--
ALTER TABLE `productos_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_productos_sucursal` (`sucursal_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `sucursal_id` (`sucursal_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `clientes_activos`
--
ALTER TABLE `clientes_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos_stock`
--
ALTER TABLE `productos_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD CONSTRAINT `detalle_pedidos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `detalle_pedidos_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos_stock` (`id`);

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos_stock` (`id`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes_activos` (`id`);

--
-- Filtros para la tabla `productos_stock`
--
ALTER TABLE `productos_stock`
  ADD CONSTRAINT `fk_productos_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  ADD CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`cliente_id`) REFERENCES `clientes_activos` (`id`),
  ADD CONSTRAINT `ventas_ibfk_4` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  ADD CONSTRAINT `ventas_ibfk_5` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
