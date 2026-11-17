# Análisis del material recibido

- Libro de dependencias: 170 filas, 170 IDs RRHH distintos, 14 DDP.
- Libro distritos: 14 DDP, IDs RRHH del 2354 al 2367.
- KML capa 01: 170 puntos; capa 03: 167 jurisdicciones; capa 04: 14 DDP.
- Polígonos sin punto: [].
- Dependencias con punto sin polígono: ['DEP-100', 'DEP-145', 'DEP-170'].
- Validación Shapely sobre fuentes geométricas: 44 geometrías con incidencia (revisar archivo CSV incluido).
- SHA256 del KML: `2cba46ecdfe5d953b1a825b059e09eb3f90ee8ffd314b1c90b5743bcd406701d`.

Códigos DEP-/DDP- son *referencias externas del KML*; el ID RRHH es la identidad institucional.
No se importan otras capas del KML. El árbol izquierdo/derecho no forma parte de la etapa 1.
