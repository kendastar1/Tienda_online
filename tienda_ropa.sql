-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 26-11-2025 a las 09:58:05
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
  `campana_id` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `actividades`
--

INSERT INTO `actividades` (`id`, `usuario_id`, `accion`, `descripcion`, `tipo`, `referencia_id`, `campana_id`, `fecha_registro`) VALUES
(1, 1, 'Sistema iniciado', 'El sistema ha sido iniciado correctamente', 'sistema', NULL, NULL, '2025-11-18 01:44:23'),
(2, 1, 'Usuario registrado', 'Usuario administrador creado', 'sistema', NULL, NULL, '2025-11-18 01:44:23'),
(3, 1, 'Usuario creado', 'Nuevo usuario registrado: kevin', 'sistema', NULL, NULL, '2025-11-18 01:46:37'),
(4, 1, 'Producto creado', 'Nuevo producto agregado: kevin', 'inventario', NULL, NULL, '2025-11-18 02:04:12'),
(5, 1, 'Producto eliminado', 'Producto eliminado: kevin', 'inventario', NULL, NULL, '2025-11-18 02:04:33'),
(6, 1, 'Producto creado', 'Nuevo producto agregado: kevin1', 'inventario', NULL, NULL, '2025-11-18 02:24:36'),
(7, 1, 'Producto actualizado', 'Producto actualizado: kevin1', 'inventario', NULL, NULL, '2025-11-18 02:40:12'),
(8, 1, 'Producto creado', 'Nuevo producto agregado: camisas', 'inventario', NULL, NULL, '2025-11-21 21:34:02'),
(9, 1, 'Producto eliminado', 'Producto eliminado: kevin1', 'inventario', NULL, NULL, '2025-11-21 21:34:20'),
(10, 1, 'Producto actualizado', 'Producto actualizado: camisas', 'inventario', NULL, NULL, '2025-11-21 21:34:35'),
(11, 1, 'Producto creado', 'Nuevo producto agregado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 22:04:32'),
(12, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 22:10:57'),
(13, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 22:11:15'),
(14, 1, 'Producto actualizado', 'Producto actualizado: camisas', 'inventario', NULL, NULL, '2025-11-21 22:11:23'),
(15, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 22:16:20'),
(16, 1, 'Producto eliminado', 'Producto eliminado: camisas', 'inventario', NULL, NULL, '2025-11-21 22:54:07'),
(17, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 22:56:12'),
(18, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 23:04:19'),
(19, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 23:28:49'),
(20, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-21 23:28:56'),
(21, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-22 00:14:45'),
(22, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-22 00:17:27'),
(23, 1, 'Usuario actualizado', 'Usuario actualizado: kevinalexander', 'sistema', NULL, NULL, '2025-11-22 00:35:03'),
(24, 1, 'Usuario creado', 'Nuevo usuario registrado: hola', 'sistema', NULL, NULL, '2025-11-22 00:35:29'),
(25, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-22 00:38:48'),
(26, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-22 00:39:13'),
(27, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-22 00:50:50'),
(28, 1, 'Sucursal creada', 'Nueva sucursal: ads', 'sistema', NULL, NULL, '2025-11-22 12:54:33'),
(29, 1, 'Sucursal eliminada', 'Sucursal eliminada: ads', 'sistema', NULL, NULL, '2025-11-22 12:54:57'),
(30, 1, 'Sucursal actualizada', 'Sucursal actualizada: Sucursal Centro', 'sistema', NULL, NULL, '2025-11-22 13:40:33'),
(31, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-22 14:48:31'),
(32, 1, 'Usuario actualizado', 'Usuario actualizado: hola1', 'sistema', NULL, NULL, '2025-11-22 14:48:41'),
(33, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-23 04:24:08'),
(34, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-23 04:31:13'),
(35, 1, 'Producto actualizado', 'Producto actualizado: kevinasd', 'inventario', NULL, NULL, '2025-11-23 04:37:08'),
(36, 2, 'Producto actualizado', 'Producto actualizado: camisa azul', 'inventario', NULL, NULL, '2025-11-23 04:42:47'),
(37, 2, 'Producto creado', 'Nuevo producto agregado: zapatos', 'inventario', NULL, NULL, '2025-11-23 04:44:02'),
(38, 1, 'Producto creado', 'Nuevo producto agregado: Vestido Negro Noche', 'inventario', NULL, NULL, '2025-11-23 04:48:09'),
(39, 1, 'Producto creado', 'Nuevo producto agregado: Bufanda de Cachemira', 'inventario', NULL, NULL, '2025-11-23 04:50:31'),
(40, 2, 'Venta registrada', 'Venta #8 procesada exitosamente. Total: $178895.2', 'venta', 8, NULL, '2025-11-23 05:33:42'),
(41, 4, 'Campaña actualizada', 'Campaña \'Campaña Descuento Back-to-School\' fue actualizada', 'sistema', NULL, NULL, '2025-11-26 01:25:32'),
(42, 4, 'Campaña actualizada', 'Campaña \'Prueba\' fue actualizada', 'sistema', NULL, NULL, '2025-11-26 03:07:38'),
(50, 5, 'Usuario actualizado', 'Usuario actualizado: Administrador Principal', 'sistema', NULL, NULL, '2025-11-26 06:09:26'),
(51, 5, 'Usuario creado', 'Nuevo usuario registrado: Camila Rueda', 'sistema', NULL, NULL, '2025-11-26 06:12:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `campanas_marketing`
--

CREATE TABLE `campanas_marketing` (
  `id` int(11) NOT NULL,
  `nombre_campana` varchar(255) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `presupuesto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo_real` decimal(10,2) DEFAULT 0.00,
  `canal` enum('redes_sociales','email','publicidad_pagada','SEO','otros','referidos') NOT NULL,
  `estado` enum('activa','pausada','finalizada') DEFAULT 'activa',
  `ingresos_generados` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `campanas_marketing`
--

INSERT INTO `campanas_marketing` (`id`, `nombre_campana`, `fecha_inicio`, `fecha_fin`, `presupuesto`, `costo_real`, `canal`, `estado`, `ingresos_generados`) VALUES
(2, 'Campaña Mala Inversión', '2025-05-01', '2025-05-31', 3000.00, 3000.00, 'redes_sociales', 'finalizada', 1500.00),
(3, 'Campaña Descuento Back-to-School', '2025-06-01', NULL, 1500.00, 5500.00, 'email', 'pausada', 35000.00),
(4, 'Programa de Referidos VIP_', '2025-06-01', '2025-11-05', 1500.00, 1500.00, 'email', 'activa', 18000.00),
(7, 'Prueba', '2025-11-14', '2025-11-21', 2324.00, 2131.00, 'redes_sociales', 'activa', 23132.00);

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
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fuente_adquisicion` varchar(100) DEFAULT 'Desconocido',
  `apellido` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes_activos`
--

INSERT INTO `clientes_activos` (`id`, `nombre`, `correo`, `telefono`, `estado`, `fecha_registro`, `fuente_adquisicion`, `apellido`, `direccion`, `ciudad`, `password`) VALUES
(2, 'María García', 'maria@email.com', '3123598028', 'activo', '2025-11-18 01:44:23', 'redes_sociales', NULL, NULL, NULL, ''),
(3, 'Carlos López', 'carlos@email.com', '555-2003', 'activo', '2025-11-18 01:44:23', 'Desconocido', NULL, NULL, NULL, ''),
(4, 'Camila Rueda', 'helena@gmail.com', '3209225480', 'activo', '2025-11-25 18:04:01', 'evento', NULL, NULL, NULL, ''),
(7, 'yusmar Carvajal', 'carvajal@empresa.com', NULL, 'activo', '2025-11-26 08:16:06', 'Desconocido', NULL, NULL, NULL, '12345678');

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
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `proveedor_id` int(11) DEFAULT NULL
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
  `categoria_id` int(11) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `codigo_qr` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_stock`
--

INSERT INTO `productos_stock` (`id`, `nombre`, `descripcion`, `precio`, `descuento`, `porcentaje_descuento`, `cantidad`, `categoria`, `categoria_id`, `sucursal_id`, `estado`, `imagen`, `fecha_creacion`, `codigo_qr`) VALUES
(9, 'camisa azul', 'camisa para caballero', 30000.00, 9000.00, 30, 44, 'camisas', 1, 2, 'activo', '692290c78fb86_1763872967.jpg', '2025-11-21 22:04:32', NULL),
(10, 'zapatos', 'zapatos clásicos', 24000.00, 5280.00, 22, 39, 'camisas', 1, 2, 'activo', '6922911285808_1763873042.webp', '2025-11-23 04:44:02', NULL),
(11, 'Vestido Negro Noche', 'vestido para dama elegante', 50000.00, 10500.00, 21, 39, 'vestidos', 3, 1, 'activo', '69229209157d9_1763873289.webp', '2025-11-23 04:48:09', NULL),
(12, 'Bufanda de Cachemira', 'bufanda para dama', 75000.00, 0.00, 0, 19, 'accesorios', 4, 1, 'activo', '69229297c35c4_1763873431.webp', '2025-11-23 04:50:31', NULL),
(14, 'traje elegante', 'camisa con pantalon elegante', 120000.00, 0.00, 0, 25, 'trajes', 5, 1, 'activo', '69237519c823d_1763931417.jpg', '2025-11-24 01:47:29', NULL),
(15, 'pantalón elegante', 'un pantalon para caballero', 50000.00, 6000.00, 12, 25, 'pantalones', 6, 1, 'activo', '69237590df29f_1763931536.webp', '2025-11-24 01:58:56', NULL),
(16, 'pantalón elegante para dama', 'un pantalón negro hermoso', 52000.00, 0.00, 0, 50, 'pantalones', 6, 1, 'activo', '692376134c12a_1763931667.webp', '2025-11-24 02:01:07', NULL),
(17, 'chaqueta', 'una chaqueta para dama elegante', 48000.00, 2880.00, 6, 60, 'chaquetas', 7, 1, 'activo', '692376b0a289f_1763931824.webp', '2025-11-24 02:03:44', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resenas_clientes`
--

CREATE TABLE `resenas_clientes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `venta_id` int(11) DEFAULT NULL,
  `calificacion` int(11) NOT NULL CHECK (`calificacion` >= 1 and `calificacion` <= 5),
  `comentario` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('activa','oculta') DEFAULT 'activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `resenas_clientes`
--

INSERT INTO `resenas_clientes` (`id`, `cliente_id`, `producto_id`, `venta_id`, `calificacion`, `comentario`, `fecha_registro`, `estado`) VALUES
(11, 2, 10, 8, 4, 'Buenos zapatos, son cómodos para el día a día. El precio con descuento fue excelente.', '2025-11-25 21:01:45', 'activa'),
(12, 4, 11, 8, 5, 'El vestido es hermoso, perfecto para ocasiones especiales. La tela es de muy buena calidad.', '2025-11-25 21:01:45', ''),
(13, 3, 12, 8, 3, 'La bufanda es bonita pero esperaba que fuera más larga. Buena calidad de material.', '2025-11-25 21:01:45', 'oculta'),
(14, 2, 9, 9, 4, 'Buena camisa para el precio. La talla es exacta y el material respirable.', '2025-11-25 21:01:45', 'activa');

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
(5, 'diseñador', 'Gestiona contenido y diseño', '2025-11-18 01:44:23'),
(6, 'gerente_marketing', 'Gestiona campañas, análisis y estrategias de marketing', '2025-11-24 20:19:58'),
(7, 'proveedor', 'Gestiona proveedores y compras de productos', '2025-11-26 07:19:23');

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
(1, 'Administrador Principal', 'admin@tiendaropa.co', '$2y$10$ws5y1FVkli5yM51WWNo98uepOAC5bQy5lTGxnXlPgBvKEY32fIzw6', 1, '2025-11-18 01:44:23', 'activo'),
(2, 'kevinalexander', 'hola@gmail.com', '123456789', 3, '2025-11-18 01:46:37', 'activo'),
(3, 'hola1', 'kevin1@gmail.com', '$2y$10$EOrh1mEym9divTLpuUvnpO.XG/WN9eGFVCvfJiP3MzS.MtvM0eoYa', 4, '2025-11-22 00:35:29', 'activo'),
(4, 'Juan Pérez', 'marketing@empresa.com', 'Juane123', 6, '2025-11-24 20:21:54', 'activo'),
(5, 'Administrador Secundario', 'admin2@tiendaropa.com', '1234', 1, '2025-11-26 05:56:56', 'activo'),
(6, 'Camila Rueda', 'cami@gmail.com', '$2y$10$zYcm0RRvixxhFwfSINTHN.bonCsln5DZV3zs3i7rwhmPvOfm3JQk.', 4, '2025-11-26 06:12:59', 'activo'),
(7, 'Juan Proveedor', 'juan@proveedor.com', 'J.P1234', 7, '2025-11-26 07:24:19', 'activo');

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
(8, NULL, NULL, 1, 2, 154220.00, 24675.20, 178895.20, 'efectivo', 200000.00, 21104.80, 'completada', '2025-11-23 05:33:42'),
(9, NULL, NULL, 1, 1, 12000.00, 1920.00, 13920.00, 'tarjeta', NULL, NULL, 'completada', '2025-06-24 05:00:00'),
(10, NULL, NULL, 2, 2, 25000.00, 4000.00, 29000.00, 'efectivo', NULL, NULL, 'completada', '2025-07-24 05:00:00'),
(11, NULL, NULL, 1, 1, 15000.00, 2400.00, 17400.00, 'tarjeta', NULL, NULL, 'completada', '2025-08-24 05:00:00'),
(12, NULL, NULL, 2, 1, 32000.00, 5120.00, 37120.00, 'tarjeta', NULL, NULL, 'completada', '2025-09-24 05:00:00'),
(13, NULL, NULL, 1, 2, 28000.00, 4480.00, 32480.00, 'efectivo', NULL, NULL, 'completada', '2025-10-24 05:00:00'),
(14, NULL, NULL, 1, 1, 45000.00, 7200.00, 52200.00, 'tarjeta', NULL, NULL, 'completada', '2025-11-24 05:00:00');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_act_campana` (`campana_id`);

--
-- Indices de la tabla `campanas_marketing`
--
ALTER TABLE `campanas_marketing`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `proveedor_id` (`proveedor_id`);

--
-- Indices de la tabla `productos_stock`
--
ALTER TABLE `productos_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_productos_sucursal` (`sucursal_id`),
  ADD KEY `fk_prod_categoria` (`categoria_id`);

--
-- Indices de la tabla `resenas_clientes`
--
ALTER TABLE `resenas_clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `venta_id` (`venta_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `campanas_marketing`
--
ALTER TABLE `campanas_marketing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `clientes_activos`
--
ALTER TABLE `clientes_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `resenas_clientes`
--
ALTER TABLE `resenas_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `actividades_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_act_campana` FOREIGN KEY (`campana_id`) REFERENCES `campanas_marketing` (`id`);

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
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes_activos` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`);

--
-- Filtros para la tabla `productos_stock`
--
ALTER TABLE `productos_stock`
  ADD CONSTRAINT `fk_prod_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  ADD CONSTRAINT `fk_productos_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`);

--
-- Filtros para la tabla `resenas_clientes`
--
ALTER TABLE `resenas_clientes`
  ADD CONSTRAINT `resenas_clientes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes_activos` (`id`),
  ADD CONSTRAINT `resenas_clientes_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos_stock` (`id`),
  ADD CONSTRAINT `resenas_clientes_ibfk_3` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`);

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
