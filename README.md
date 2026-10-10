# SaludUSM – Tarea 2 INF-239 Bases de Datos (PHP + MySQL)

Plataforma web de gestión clínica de la red de salud SaludUSM: agendamiento de citas, registro de atenciones (diagnósticos y recetas) y administración de médicos y centros.

## 1. Integrantes

| Nombre | Rol USM |
|---|---|
| Francisca Catalán | 202473656-6 |
| nombre | rol |

**Repositorio GitHub:** (poner repositorio)


## 2. Estructura de la entrega

```
T2-ROL1-ROL2.zip
├── PDF/   Normalización a 3FN (pasos 1FN, 2FN y 3FN)
├── PHP/   Todos los archivos .php
├── BD/    bd_saludusm.sql (tablas, function, procedimientos, triggers, views y datos de prueba)
└── README.md
```

Archivos de `PHP/`:

| Archivo | Función |
|---|---|
| `login.php`, `logout.php`, `registro.php`, `index.php` | Inicio y cierre de sesión, registro de pacientes y redirección según el rol |
| `paciente_home.php`, `medico_home.php`, `admin_home.php` | Página principal de cada rol (el administrador ve aquí el panel de gestión) |
| `perfil.php` | Ver y modificar datos, cambiar contraseña y (paciente) eliminar la cuenta |
| `agendar.php`, `mis_citas.php`, `reprogramar.php` | Paciente: agendar, ver, reprogramar y cancelar citas |
| `agenda.php`, `atencion.php` | Médico: agenda del día, cambio de estado y registro de atención, diagnósticos y recetas |
| `historial.php` | Historial clínico (solo lectura) |
| `buscar_medicos.php`, `busqueda_avanzada.php` | Buscador de médicos y búsqueda avanzada de citas |
| `admin_centros.php`, `admin_medicos.php` | Administrador: CRUD de centros y de médicos |
| `conexion.php`, `auth.php`, `funciones.php`, `cabecera.php`, `pie.php` | Archivos compartidos |

## 3. Instrucciones de ejecución

1. Instalar un servidor local con Apache, PHP y MySQL (XAMPP en Windows/Linux o MAMP en Mac) y encender Apache y MySQL.
2. Copiar la carpeta `PHP` dentro de la carpeta pública del servidor (`htdocs`), por ejemplo `htdocs/saludusm/PHP`.
3. Abrir **phpMyAdmin**, ir a la pestaña **Importar** y cargar `BD/bd_saludusm.sql`. El script crea la base de datos `saludusm` (no hace falta crearla antes), todas las tablas, los objetos de programación y los datos de prueba. Se puede volver a importar cuando se quiera: borra y recrea las tablas.
4. Revisar los datos de conexión en la parte superior de `PHP/conexion.php` y ajustarlos según el servidor:

   | Servidor | Host | Puerto | Usuario | Contraseña |
   |---|---|---|---|---|
   | MAMP (configuración entregada) | localhost | 8889 | root | root |
   | XAMPP | localhost | 3306 | root | (vacía) |

5. Abrir en el navegador `http://localhost/saludusm/PHP/login.php` (con MAMP, `http://localhost:8888/saludusm/PHP/login.php`).

## 4. Usuarios de prueba

El inicio de sesión se hace con **RUT + contraseña + tipo de usuario** (Paciente, Médico o Administrador).

| Rol | RUT | Contraseña | Observación |
|---|---|---|---|
| Paciente | `19876543-2` | `paciente123` | Juan Pérez. Tiene citas pasadas, futuras y atenciones |
| Paciente | `20123456-7` | `paciente123` | Ana Silva |
| Paciente | `18234567-8` | `paciente123` | Pedro Morales |
| Paciente | `17345678-9` | `paciente123` | Luisa Fernández |
| Paciente | `21456789-0` | `paciente123` | Diego Araya |
| Médico | `12345678-9` | `medico123` | **Dr. Roberto Gómez: 2 especialidades** (Cardiología, Medicina General) y **2 centros** (Casa Central, San Joaquín) |
| Médico | `98765432-1` | `medico123` | Dra. María Elena: Pediatría; Casa Central y Viña del Mar |
| Médico | `15555444-3` | `medico123` | Dr. Carlos Mendoza: Traumatología y Kinesiología; San Joaquín y Concepción |
| Médico | `16666777-8` | `medico123` | Dra. Ana Torres: Dermatología; Casa Central |
| Médico | `14444333-2` | `medico123` | Dr. Ignacio Vera: Neurología y Oftalmología; Concepción y Viña del Mar (sin citas) |
| Administrador | `11111111-1` | `admin123` | |
| Administrador | `22222222-2` | `admin123` | |

Las contraseñas se guardan en la base de datos con `password_hash` (bcrypt); las de esta tabla son solo para probar.

## 5. Funcionalidades por rol

**Paciente:** se registra desde el login; ve, modifica y elimina su cuenta; agenda, reprograma y cancela sus citas; ve sus citas próximas e históricas; consulta su historial de atenciones y recetas (solo lectura); busca médicos y usa la búsqueda avanzada (solo ve sus citas).

**Médico:** (creado por el administrador) ve y modifica sus datos; revisa su agenda por día; cambia el estado de sus citas; registra la atención (motivo, observaciones, diagnósticos y recetas, con crear/editar/eliminar); consulta el historial de sus pacientes; busca médicos y usa la búsqueda avanzada (solo ve sus citas).

**Administrador:** ve y modifica sus datos; gestiona centros médicos, médicos, sus especialidades (1 a 3) y los centros donde atienden; ve el panel de gestión (total de citas y % de inasistencia por centro, y los 5 diagnósticos más frecuentes); busca médicos y usa la búsqueda avanzada (ve todas las citas).

**Todos los roles** tienen una barra de búsqueda de médicos (por trozo de nombre o de especialidad) en el menú superior, y un menú de navegación según su rol.

## 6. Base de datos

Tablas (14), todas con claves primarias y foráneas:

- **Dominio:** `prevision`, `estado_cita`, `especialidad`.
- **Entidades:** `centro_medico`, `paciente`, `medico`, `administrador`, `cita`, `atencion`, `diagnostico`, `receta`.
- **Relaciones N:M:** `medico_especialidad`, `medico_centro`, `atencion_diagnostico`.

### Objetos de programación y dónde se usan

| Objeto | Tipo | Para qué sirve | Dónde se usa |
|---|---|---|---|
| `porcentaje_inasistencia(id_centro)` | Function | Porcentaje de citas "No Asistió" de un centro | Panel de gestión (a través de `vista_panel_centros`) |
| `bloque_disponible(id_medico, fecha_hora)` | Function | Indica si el bloque horario del médico está libre | Agendar hora y reprogramar |
| `sp_agendar_cita(...)` | Procedimiento | Crea una cita en estado Reservada (rechaza fechas pasadas) | Agendar hora |
| `sp_reprogramar_cita(...)` | Procedimiento | Cambia fecha/hora de una cita propia, Reservada o Confirmada y no vencida | Reprogramar cita |
| `actualizar_citas_vencidas()` | Procedimiento | Pasa a Cancelada las citas Reservadas/Confirmadas de días anteriores | Cada página protegida (ver sección 9) |
| `trg_cita_before_insert` | Trigger | Al crear una cita valida: el médico tiene la especialidad, atiende en el centro, y no hay choque de horario del médico ni del paciente | Agendar hora |
| `trg_cita_before_update` | Trigger | Mismas validaciones de choque de horario al reprogramar | Reprogramar cita |
| `trg_atencion_before_insert` | Trigger | Solo permite crear una atención si la cita está Atendida | Registro de atención |
| `trg_medico_especialidad_before_insert` | Trigger | Máximo 3 especialidades por médico | CRUD de médicos |
| `trg_medico_especialidad_before_delete` | Trigger | Impide quitar una especialidad si el médico tiene citas futuras con ella | CRUD de médicos |
| `trg_medico_centro_before_delete` | Trigger | Impide quitar un centro si el médico tiene citas futuras en él | CRUD de médicos |
| `vista_citas_detalle` | View | Cita con paciente, previsión, médico, especialidad, centro, región y estado | Mis citas, agenda, búsqueda avanzada |
| `vista_historial_clinico` | View | Una fila por atención, con diagnósticos y recetas agrupados | Historial clínico |
| `vista_panel_centros` | View | Total de citas y % de inasistencia por centro | Panel de gestión |
| `vista_top_diagnosticos` | View | Los 5 diagnósticos más frecuentes de la red | Panel de gestión |

Los mensajes de error de los triggers y procedimientos están en español y se muestran tal cual al usuario cuando intenta una acción que infringe una regla.

## 7. Reglas de negocio y cómo se cumplen

- **Sin sobre-agendamiento del médico:** trigger de cita; un médico no puede tener dos citas activas (no canceladas) en la misma fecha y hora.
- **Paciente sin dos citas a la misma hora:** mismo trigger.
- **El médico debe tener la especialidad y atender en el centro elegido:** trigger de cita; además la pantalla de agendar solo ofrece combinaciones válidas.
- **Solo se modifican o cancelan citas Reservadas o Confirmadas con fecha que no haya pasado:** validado en `mis_citas.php`, `reprogramar.php` y `sp_reprogramar_cita`.
- **El médico solo cambia el estado de sus propias citas** a Confirmada, Atendida o No Asistió.
- **Una atención solo puede existir si la cita está Atendida y es del médico que la registra:** trigger de atención y validación en `atencion.php`.
- **No hay recetas sin atención:** la clave foránea `receta.id_atencion` es obligatoria, con eliminación en cascada.
- **Un médico tiene entre 1 y 3 especialidades y al menos 1 centro:** el formulario del administrador valida ambos límites y el máximo de 3 lo valida además un trigger.

## 8. Respuestas a lo que pide el enunciado

**Duración de los bloques y horario de atención:** bloques de **30 minutos**, de **08:00 a 18:00** (el último bloque parte a las 17:30). Se considera atención todos los días de la semana; no se excluyen fines de semana ni feriados.

**Cuándo se ejecuta la actualización de citas vencidas:** el procedimiento `actualizar_citas_vencidas()` se ejecuta **cada vez que un usuario autenticado entra a una página protegida** (dentro de `requerir_rol()`, en `PHP/auth.php`). Una cita se considera vencida cuando su **día ya pasó** (no a la hora exacta), para que el médico pueda marcar como Atendida o No Asistió las citas del mismo día.

**Qué ocurre con las citas al eliminar la cuenta de un paciente:** se **eliminan también todas sus citas** y, en cascada, sus atenciones, diagnósticos asociados y recetas (`ON DELETE CASCADE`). Para eliminar la cuenta hay que confirmar la acción y escribir la contraseña.

**Qué ocurre al quitar una especialidad o un centro a un médico con citas futuras:** el sistema **lo impide** y muestra el mensaje "no se puede quitar la especialidad/el centro, el médico tiene citas futuras asociadas" (triggers de `medico_especialidad` y `medico_centro`). Se consideran citas futuras las Reservadas o Confirmadas con fecha desde ahora en adelante. Como la edición del médico se hace en una transacción, si algo falla no se guarda ningún cambio.

**Requisitos de la contraseña:** mínimo **6 caracteres** y al menos **2 números** (máximo 72 caracteres). Se exige al registrarse, al cambiarla desde el perfil y al crear un médico.

**Validaciones del registro de pacientes:** campos obligatorios (RUT, nombre, e-mail, fecha de nacimiento, sexo, comuna, previsión y contraseña; el teléfono es opcional), formato de RUT `XXXXXXXX-X`, e-mail con formato válido, **RUT único** y **e-mail único**, fecha de nacimiento no futura, y contraseña según los requisitos anteriores con confirmación.

## 9. Supuestos

1. **RUT:** se guarda sin puntos y con guion (`XXXXXXXX-X`, 7 u 8 dígitos). Se valida el formato pero **no el dígito verificador**, ya que el enunciado solo pide el formato. La letra K se guarda en mayúscula.
2. **Inicio de sesión:** se usa RUT + contraseña + tipo de usuario (cada rol tiene su propia tabla: `paciente`, `medico` y `administrador`). El e-mail es único dentro de cada tabla.
3. **Contraseñas:** se almacenan con `password_hash` (bcrypt) y se verifican con `password_verify`.
4. **Identificador del médico:** la vista del comprobante solo muestra el nombre del médico; para poder normalizar sin usar nombres como clave se asumió el RUT del médico como atributo único (`rut`), con una clave artificial `id_medico`.
5. **Comuna de residencia del paciente:** no aparece en las vistas del anexo, pero se mantiene en `paciente` por coherencia con el modelo de la Tarea 1.
6. **Fecha y hora de la cita:** aparecen separadas en el comprobante, pero se unificaron en un solo atributo `fecha_hora` (DATETIME), igual que en la Tarea 1.
7. **Fecha de la atención:** se guarda como la fecha y hora en que el médico registra la atención.
8. **Una atención por cita:** `atencion.id_cita` es único.
9. **Cita cancelada libera el bloque:** el médico y el paciente pueden volver a usar esa fecha y hora.
10. **Un médico no puede estar en dos citas a la misma hora**, aunque sean en centros distintos.
11. **Estados de la cita:** Reservada es el estado inicial. El médico solo puede **confirmar** citas Reservadas que aún no han pasado, y marcar **Atendida** o **No Asistió** citas Reservadas o Confirmadas del día de hoy o de días anteriores.
12. **Diagnósticos:** se registran con código CIE-10 (formato letra, dos dígitos y opcionalmente un punto con extensión, por ejemplo `J00` o `E11.9`) y descripción. Si el código ya existe en la base se reutiliza el diagnóstico existente (se conserva su descripción original); si no existe, se crea.
13. **Historial clínico del médico:** el médico puede ver las atenciones de los pacientes con los que tiene o tuvo citas, incluyendo las realizadas por otros médicos, para conocer los antecedentes. No puede modificar las de otros.
14. **Eliminación de médicos y centros:** no se puede eliminar un médico ni un centro que tenga citas registradas (`ON DELETE RESTRICT`); el sistema muestra un mensaje explicándolo.
15. **Perfil:** el RUT no se puede modificar (es el identificador de inicio de sesión). Los médicos no pueden cambiar sus propias especialidades ni centros: los gestiona el administrador. El administrador asigna una contraseña inicial al crear un médico y puede restablecerla al editarlo.
16. **Búsqueda avanzada:** muestra un máximo de 500 citas, ordenadas de la más reciente a la más antigua.
17. **Zona horaria:** `America/Santiago`, tanto en PHP como en la sesión de MySQL.
18. **Datos de prueba con fechas fijas:** las citas de prueba tienen fechas entre septiembre y octubre de 2026. Algunas citas futuras de prueba pueden pasar a estado Cancelada una vez que su día haya pasado; para seguir probando, agendar citas nuevas desde la plataforma. Existe una cita Reservada con fecha de septiembre justamente para comprobar la actualización de citas vencidas.
19. **Estilos:** Bootstrap se carga desde un CDN, por lo que se necesita internet para ver el diseño completo.

## 10. Cómo probar cada punto del enunciado

| Punto | Cómo probarlo |
|---|---|
| 3.3 Login y registro | Registrar un paciente nuevo; probar RUT mal formado, RUT/e-mail repetido y contraseña débil |
| 3.3 Perfil | En `Mi perfil`: modificar datos, cambiar contraseña y, como paciente, eliminar la cuenta |
| 3.4.1 Buscador | Escribir `Cardio` o `Roberto` en la barra superior |
| 3.4.2 Mis citas | Entrar como el paciente `19876543-2` |
| 3.4.3 Agenda | Entrar como un médico y elegir un día con citas (por ejemplo, 2026-10-20 con el Dr. Roberto) |
| 3.4.4 Agendar hora | Como paciente, elegir especialidad, centro, médico y fecha; probar un bloque ya ocupado |
| 3.4.5 Panel de gestión | Como administrador, en la página de inicio (hay más de 5 diagnósticos: se muestran solo los 5 más frecuentes) |
| 3.5 Búsqueda avanzada | Combinar filtros con cada rol y comprobar que cada uno ve solo lo suyo |
| 3.6 CRUD de citas | Reprogramar y cancelar como paciente; intentar sobre-agendar para ver el mensaje |
| 3.6 Citas vencidas | Entrar a cualquier página protegida: la cita Reservada del 01-09-2026 pasa a Cancelada |
| 3.7 Atenciones | Como médico, marcar una cita como Atendida y registrar atención, diagnósticos y recetas |
| 3.8 Médicos y centros | Como administrador, intentar quitar una especialidad a un médico con citas futuras (por ejemplo, Cardiología al Dr. Roberto) |