# Harness de diseño y rediseño de Dashboards — SIGDE

Guía metodológica y técnica permanente para la construcción, auditoría y rediseño
del dashboard de CADA rol del sistema (`docente`, `secretaria`, `directivo`, `coordinador`, `admin`).

Cada rol posee responsabilidades y flujos de trabajo únicos; no obstante, **todos comparten
la misma gramática visual, los mismos componentes reutilizables y las mismas reglas de integridad de datos.**

---

## 1. Proceso por rol (Paso a paso estricto)

Para abordar el dashboard de cualquier rol se debe seguir esta secuencia obligatoria,
**deteniéndose y esperando aprobación del usuario entre cada etapa**:

### a) Inventario del flujo del rol
1. Listar todas las opciones del menú lateral del rol y sus rutas reales (`/modulo/...`).
2. Describir pantalla por pantalla: qué datos presenta, qué acciones permite (lectura/escritura) y qué KPIs o resúmenes contiene.
3. Identificar la **tarea principal** (la operación crítica y recurrente del día a día) y las **tareas ocasionales** (ej. fin de lapso, inscripciones, auditoría).

### b) Inventario del dashboard actual
Listar cada elemento existente (tarjetas, gráficos, tablas, accesos directos, avisos) y clasificarlo con una de estas 4 etiquetas:
- `[DUPLICADO EXTERNO]`: El dato ya vive exactamente igual en una pantalla propia accesible desde el menú lateral.
- `[DUPLICADO INTERNO]`: El dato se repite dentro del mismo dashboard (ej. en una stat-card y simultáneamente en una gráfica o alerta).
- `[SIN ACCIÓN]`: Muestra un estado o conteo pasivo que no le permite al rol tomar ninguna decisión ni resolver nada.
- `[ÚTIL]`: Cumple con el criterio de admisión y aporta valor ejecutivo/operativo al rol.

### c) Criterio de admisión de datos
Un dato **solo tiene derecho a entrar al dashboard** si cumple al menos una de estas 3 condiciones:
1. **Resume y agrega** información dispersa que de otro modo obligaría al usuario a navegar por múltiples pantallas para obtener una visión global.
2. **Alerta de una situación pendiente o anómala** que exige una acción correctiva directa e inmediata de ese rol específico.
3. **Ofrece un punto de partida directo** hacia su tarea principal diaria.

**Filtros de exclusión:**
- No se repite en ninguna otra sección del propio dashboard.
- No es una copia en miniatura de una pantalla de estadísticas, reportes o tablas maestras existentes.

### d) Informe de propuesta en tabla comparativa
Se presenta al usuario el resultado del inventario en una tabla clara:
`Elemento | Estado Actual | Clasificación | Justificación / Destino propuesto`.
> **ALTO:** No se escribe ni modifica código hasta que el usuario revise, ajuste y apruebe formalmente la propuesta.

### e) Ejecución por fases
Una vez aprobada la propuesta, la implementación se realiza en 3 fases secuenciales, reportando y verificando al finalizar cada una:
- **Fase 1 (Modelo y Controlador):** Creación/ajuste de métodos de consulta pura en el modelo (sin HTML, sin UI) y preparación de variables en el controlador.
- **Fase 2 (Vistas, Estilos y Gráficas):** Maquetación de partials limpios, configuración de componentes de UI, estilos Sass compilados y gráficas con Chart.js.
- **Fase 3 (Limpieza final y verificación):** Eliminación de código y consultas obsoletas, verificación de dependencias compartidas con otros roles y ejecución de suites de verificación (`npm run verificar`).

---

## 2. Estructura visual de un Dashboard (Orden fijo)

Todo dashboard en SIGDE sigue una jerarquía visual vertical estandarizada:

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Encabezado (Saludo, Badge de rol, Period Chip, Fecha)       │
│    [Banner condicional si no hay año escolar activo]            │
├─────────────────────────────────────────────────────────────────┤
│ 2. Stat-Cards (Solo totales agregados, máx. 3 o 4)             │
├─────────────────────────────────────────────────────────────────┤
│ 3. Gráficas (Data-panels con desgloses analíticos)             │
├─────────────────────────────────────────────────────────────────┤
│ 4. Centro de Atención Inmediata (Alertas procesables)          │
└─────────────────────────────────────────────────────────────────┘
```

### a) Encabezado
- **Saludo personalizado:** `¡Hola, [Nombre]!` utilizando la sesión activa.
- **Badge de rol:** Etiqueta visual con el rol autenticado.
- **Period chip:** Badge que identifica el año escolar en curso (`2025-2026`).
- **Fecha:** Fecha actual formateada en español.
- **Banner de advertencia condicional:** Solo visible si el sistema detecta que no hay ningún año escolar marcado en curso.

### b) Stat-Cards (Fila superior de KPIs)
- Muestran **únicamente valores totales absolutos o porcentuales globales** (ej. total matrícula, % capacidad ocupada, total personal activo).
- **Prohibido incluir desgloses dentro de la tarjeta** (los desgloses por sexo, grado o cargo pertenecen a las gráficas).
- Máximo 3 a 4 tarjetas por dashboard para evitar sobrecarga cognitiva.
- No llevan botones de acción ni enlaces dispersos; el acceso al módulo completo ya vive en el menú lateral.

### c) Gráficas analíticas (Zona central en componentes `data-panel`)
- Cada gráfica vive dentro de un `<div class="data-panel">` con título, subtítulo e icono semántico.
- Muestran el **desglose visual** de los totales presentados en las stat-cards:
  - **Barras agrupadas:** Para relaciones complejas bivariadas (ej. alumnos inscritos vs. capacidad física por grado).
  - **Dona (Doughnut):** Para composición categórica de un total (ej. distribución de personal por tipo de cargo, distribución de matrícula por sexo).
  - **Línea temporal:** **Únicamente si existen 3 o más períodos históricos registrados**. Si hay menos de 3 períodos, no aporta valor de tendencia y queda excluida.
- Requisitos técnicos de cada gráfica:
  - `id` semántico único en el elemento `<canvas>`.
  - Atributo `aria-label` descriptivo y texto accesible de respaldo en el contenedor.
  - Atributo `data-chart-config` con JSON escapado (`htmlspecialchars(json_encode(...))`) conteniendo labels, data y tipos.
  - Manejo de estado vacío si la serie no tiene registros (`count === 0`).

### d) Atención inmediata (Panel inferior o alerta interactiva)
- Destinado **exclusivamente a situaciones pendientes que requieren intervención directa del rol**.
- Estructura de cada alerta:
  1. Conteo destacado en badge.
  2. Mensaje descriptivo claro de la anomalía o pendiente.
  3. **Botón de acción directa** que conduce a la pantalla exacta donde se resuelve el problema (ej. "Resolver retiros", "Asignar titular").
- **Estado vacío:** Si no hay pendientes, muestra un banner o panel positivo neutral: `"Sin pendientes hoy"`.
- **Condicionalidad temporal:** Ciertas alertas solo se consultan y renderizan en períodos específicos del calendario (ej. "Carga de literales pendiente" solo aplica en cierre de lapso/año).

### e) Elementos prohibidos en el Dashboard
- **Accesos directos redundantes:** Prohibido agregar tarjetas de "Acciones rápidas" o botones para "Ir a lista de alumnos", "Ir a secciones", etc. El menú lateral ya ofrece acceso en un solo clic.
- **Tablas maestras completas:** Prohibido renderizar tablas paginadas o listados exhaustivos que ya tienen su propia vista en el sistema.
- **Tarjetas operativas duplicadas:** Prohibido crear tarjetas que repitan acciones ya presentes en las pantallas funcionales.

---

## 3. Reglas de Integridad de Datos

1. **Un dato, un único lugar:**
   - La **stat-card** da el total macro.
   - La **gráfica** da el desglose visual.
   - La **alerta** avisa de la anomalía pendiente.
   *Ningún dato puede figurar simultáneamente en dos componentes del dashboard.*
2. **Sin duplicidad externa:**
   - El dashboard no es una vista duplicada de un módulo. No debe copiar tablas ni reportes existentes.
3. **Alertas estrictamente accionables y pertinentes:**
   - Cada alerta debe pertenecer al rol que tiene permisos y competencia para resolverla. Si el rol no puede solucionarlo, la alerta no debe aparecer en su panel.
   - Toda alerta contiene un botón de resolución directa, no meramente informativo.

---

## 4. Reglas Técnicas y Estándares de Código

### a) MVC Estricto
- **Modelos (`app/Models/*DashboardModel.php`):**
  - Contienen todas las consultas SQL (`PDO`).
  - Devuelven **datos puros de negocio** (enteros, arreglos asociativos de conteos).
  - **Nunca devuelven cadenas HTML, iconos Bootstrap (`bi-*`), textos de botones ni URLs.**
- **Controlador (`app/Controllers/DashboardController.php`):**
  - Orquesta la obtención de datos llamando a los modelos.
  - Determina el rol de la sesión y prepara las variables correspondientes.
  - Pasa los datos estructurados a la vista.
- **Vistas (`app/Views/dashboard/<rol>/`):**
  - Responsabilidad exclusiva de presentación.
  - Traducen los datos puros a badges, clases semánticas, iconos y URLs de navegación.

### b) Consultas SQL eficientes y reutilización
- Si una stat-card y una gráfica se alimentan de la misma entidad (ej. total de matrícula y distribución por grado), el modelo calcula ambas métricas en una única consulta agregada o reutiliza el conjunto de datos en memoria para evitar viajes redundantes a la base de datos.

### c) Memoización en modelos
- Consultas que puedan ser solicitadas en múltiples puntos del ciclo de vida de la petición (ej. `getRetirosPendientes()`, `getIdAnioActivo()`) implementan caché interna indexada por ID de año.

### d) Arquitectura modular de vistas (Partials)
- El archivo orquestador principal (`app/Views/dashboard/<rol>/index.php`) debe ser conciso (**menos de 250 líneas**).
- Se divide en parciales especializados dentro de `partials/`:
  - `indicadores.php`: Stat-cards de totales.
  - `graficas.php`: Paneles con lienzos de gráficas.
  - `atencion.php`: Centro de atención inmediata y alertas.
  - Otros parciales específicos del rol cuando aplique.

### e) Gráficas con Chart.js 4.4.8
- **Librería vendor:** Fija en `package.json` (`"chart.js": "4.4.8"`). Se distribuye a través del script `npm run copy:vendor-js` hacia `public/js/vendor/chart.js`.
- **Módulo JavaScript compartido:** `public/js/modules/dashboard/charts.js`. Se inicializa automáticamente al cargar la vista escaneando los lienzos con atributos `data-chart-config`.
- **Paleta y variables CSS (`resources/scss/components/_charts.scss`):**
  - Los colores de las series no usan colores semánticos de estado (`success`, `warning`, `danger`), sino variables de categoría (`--chart-1` a `--chart-6`) derivadas mediante Sass de la paleta oficial (`$primary`, `$info`, `$secondary`).
  - El módulo JS lee estas variables en tiempo de ejecución con `window.getComputedStyle(document.documentElement)`.
- **Inclusión en layouts:** Se inyectan únicamente en vistas que las requieran mediante la variable `$extraScripts`:
  ```php
  $extraScripts = [
      '/js/vendor/chart.js',
      '/js/modules/dashboard/charts.js',
  ];
  ```

### f) Accesibilidad y buenas prácticas
- Todo `<canvas>` incluye un atributo `aria-label` descriptivo de la gráfica y un elemento accesible interno para lectores de pantalla.
- Contenedores con altura relativa (`chart-container`) y `maintainAspectRatio: false` para diseño completamente responsivo.

### g) Verificaciones de integridad automáticas
Tras modificar vistas, assets, rutas o controladores de un dashboard, deben ejecutarse las herramientas de validación:
```bash
npm run verificar
```
El script debe concluir con **0 errores**.

---

## 5. Estado Actual de los Dashboards por Rol

| Rol | Vista Principal | Estado | Próximo Paso |
|---|---|---|---|
| **Directivo** | `app/Views/dashboard/directivo/index.php` | **Completado** (Fases 1, 2 y 3 listas; partials `indicadores.php`, `graficas.php`, `atencion.php`). | Rediseño concluido y auditado. |
| **Coordinador** | `app/Views/dashboard/coordinador/index.php` | **Completado** (Fases 1, 2 y 3 listas; vista propia con `indicadores.php`, `graficas.php`, `atencion.php`). | Rediseño concluido y auditado. |
| **Secretaría** | `app/Views/dashboard/secretaria/index.php` | **Completado** (Fases 1, 2 y 3 listas; partials `indicadores.php`, `graficas.php`, `atencion.php`). | Rediseño concluido y auditado. |
| **Docente** | `app/Views/dashboard/docente/index.php` | **Completado** (Fases 1, 2 y 3 listas; partials `indicadores.php`, `graficas.php`, `atencion.php`, `cumpleanos.php`). | Rediseño concluido y auditado. |
| **Admin** | `app/Views/dashboard/admin/index.php` | **Completado** (Fases 1, 2 y 3 listas; vista modular con `indicadores.php`, `graficas.php`, `atencion.php`). | Rediseño concluido y auditado. |
