<?php namespace Genius\Container;

// Exceptions
use Genius\Container\Exception\ParameterNotFoundException;
use Genius\Container\Exception\ServiceNotFoundException;
use Genius\Container\Exception\ContainerException;
// Referencias
use Genius\Container\Reference\ParameterReference;
use Genius\Container\Reference\ServiceReference;
// Interfaces
use Genius\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private $services;
    private $parameters;
    private $serviceStore;

    public function __construct(array $services = [], array $parameters = [])
    {
        $this->services     = $services;
        $this->parameters   = $parameters;
        $this->serviceStore = [];
    }

    # Comprobamos si tenemos un servicio con un nombre determinado
    public function has($name)
    {
      return isset($this->services[$name]);
    }

    # Obtener servicio por su nombre
    public function get($name)
    {
        if (!$this->has($name)) {
          // Si este servicio no esta definido en nuestra lista de servicios
            throw new ServiceNotFoundException('Service not found: '.$name);
        }

        if (!isset($this->serviceStore[$name])) {
          // Creamos este servicio si aun no lo hemos hecho
            $this->serviceStore[$name] = $this->createService($name);
        }

        // Devolvemos el servicio si ya existe
        return $this->serviceStore[$name];
    }

    # Obtener parametro
    public function getParameter($name)
    {
        // Obtenemos los nombres de los parametros separados por '.' asumiendo los enviamos como "foo.bar" (foo = parametro 1, bar = parametro 2)
        $tokens  = explode('.', $name);
        $context = $this->parameters;

        // TODO: comentar esto, por que no lo entendi
        while (null !== ($token = array_shift($tokens))) {
            if (!isset($context[$token])) {
                throw new ParameterNotFoundException('Parameter not found: '.$name);
            }

            $context = $context[$token];
        }

        return $context;
    }

    # Crear servicio por nombre
    private function createService($name)
    {
         // Valor de referencia de $this->services[$name] a "entry" para que funcione como un alias
         $entry = &$this->services[$name];

         if (!is_array($entry) || !isset($entry['class'])) {
            /*
              El "slot" del servicio debe ser un arreglo con la clase 'class'
              dentro, esta es la forma en que debe estar registrado un servicio
              en nuestro mapa de servicios */
             throw new ContainerException($name.' service entry must be an array containing a \'class\' key');
         } elseif (!class_exists($entry['class'])) {
           /*
              Si no existe la clase almacenada dentro de 'class' entonces devolvemos un error tambien
              */
             throw new ContainerException($name.' service class does not exist: '.$entry['class']);
         } elseif (isset($entry['lock'])) {
           /*
              Llamamos a esto por si ya hemos ejecutado este metodo anteriormente
              sin resolverse y este termino llamando a este metodo para el mismo metodo
                - señor simpson si quiere matarse tambien vendo armas
              */
             throw new ContainerException($name.' service contains a circular reference');
         }

         // He aqui la proteccion contra ciclos infinitos de la que hablaba!
         $entry['lock'] = true;

         /* Si el contenedor tiene argumentos, resolvemos los argumentos
            (como posiblemente sean otras clases posiblemente haya que volver
            a ejecutar este metodo, alli es donde entra la proteccion
            contra ciclos infinitos)
            */
         $arguments = isset($entry['arguments']) ? $this->resolveArguments($name, $entry['arguments']) : [];

         /*
          "Reflection (o reflexión) es cuando un objeto es capaz de examinarse
          a sí mismo de forma retrospectiva para informarte de sus métodos,
          propiedades o clases durante la ejecución del script.
          Es una funcionalidad importante y usada con frecuencia
          en el desarrollo de aplicaciones."
          Fuente: https://diego.com.es/reflection-en-php#:~:text=Reflection%20(o%20reflexión)%20es%20cuando,en%20el%20desarrollo%20de%20aplicaciones.
         */
         /* De forma simple, creamos un reflector por que el nos permite
            obtener y al parecer modificar informacion del a clase en forma de
            "codigo"
          */
         $reflector = new \ReflectionClass($entry['class']);
         // Este reflector crea una nueva instancia de la clase pero a partir de los argumentos dados
         $service = $reflector->newInstanceArgs($arguments);

         // TODO: comentar esto cuando sepa para que funciona
         if (isset($entry['calls'])) {
             $this->initializeService($service, $name, $entry['calls']);
         }

         return $service;

    }

    # Resuelve un arreglo de parametros (pueden ser servicios o parametros) y los converite en valores PHP
    private function resolveArguments($name, array $argumentDefinitions)
     {
         $arguments = [];

         foreach ($argumentDefinitions as $argumentDefinition) {
             if ($argumentDefinition instanceof ServiceReference) {
               // Si es un servicio
                 $argumentServiceName = $argumentDefinition->getName();
                 $arguments[] = $this->get($argumentServiceName);
             } elseif ($argumentDefinition instanceof ParameterReference) {
               // Si es un argumento
                 $argumentParameterName = $argumentDefinition->getName();
                 $arguments[] = $this->getParameter($argumentParameterName);
             } else {
               // Si es un... ¿valor resuelto?
                 $arguments[] = $argumentDefinition;
             }
         }

         return $arguments;
     }

     # !???
     private function initializeService($service, $name, array $callDefinitions)
      {
          foreach ($callDefinitions as $callDefinition) {
              if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                  throw new ContainerException($name.' service calls must be arrays containing a \'method\' key');
              } elseif (!is_callable([$service, $callDefinition['method']])) {
                  throw new ContainerException($name.' service asks for call to uncallable method: '.$callDefinition['method']);
              }

              $arguments = isset($callDefinition['arguments']) ? $this->resolveArguments($name, $callDefinition['arguments']) : [];

              call_user_func_array([$service, $callDefinition['method']], $arguments);
          }
      }

      /**
       * {@inheritDoc}
       */
      public function hasParameter($name)
      {
          try {
              $this->getParameter($name);
          } catch (ParameterNotFoundException $exception) {
              return false;
          }

          return true;
      }

}

// Based In: https://www.sitepoint.com/how-to-build-your-own-dependency-injection-container/
