<?php declare(strict_types = 1);

namespace arhone\construction\builder;

/**
 * Внедрение зависимостей
 *
 * Class Builder
 * @package arhone\construction\builder
 * @author Алексей Арх <info@arh.one>
 */
class Builder implements BuilderInterface {

    /**
     * Конфигурация класса
     *
     * @var array
     */
    protected static $config = [
        'new'   => false,
        'clone' => true
    ];

    /**
     * Инструкции внедрения зависимостей
     *
     * @var array
     */
    protected static $instruction = [];

    /**
     * Хранилище инъекций
     *
     * @var array
     */
    protected static $storage = [];

    /**
     * Лог сборки
     * 
     * @var array
     */
    protected static $log = [];

    /**
     * Builder constructor.
     *
     * @param array $config
     */
    public function __construct (array $config = []) {

        self::config($config);

    }

    /**
     * Возвращает результат сборки
     *
     * @param string|array $instruction
     * @return mixed
     * @throws \Exception
     */
    public static function make ($instruction) {

        if (is_string($instruction)) {
            $instruction = isset(self::$instruction[$instruction]) ? ['alias' => $instruction] : ['error' => $instruction];
        }

        $type = key($instruction);
        if (!$type) {
            return self::makeAlias([
                'alias' => current($instruction)
            ]);
        }

        $type = 'data';
        foreach (['class', 'error', 'object', 'alias', 'callback', 'array', 'string', 'integer', 'float', 'bool', 'include', 'instruction'] as $key => $value) {
            if (isset($instruction[$value])) {
                $type = $value; break;
            }
        }

        $method = 'make' . ucfirst($type);
        return self::$method($instruction);

    }

    /**
     * Возвращает результат сборки настроек
     *
     * @param array $instruction
     * @return array
     * @throws \Exception
     */
    protected static function makeAll (array $instruction = []) : array {

        $arg = [];
        foreach ($instruction as $instruct) {
            $arg[] = self::make($instruct);
        }

        return $arg;

    }

    /**
     * Создаёт и возвращает готовый экземпляр класса
     *
     * @param array $instruction
     * @param null $alias
     * @return mixed
     * @throws \Exception
     */
    protected static function makeClass (array $instruction, $alias = null) {

        if (($instruction['new'] ?? self::$config['new']) || !isset(self::$storage[$alias])) {

            if (isset($instruction['require'])) {

                if (file_exists($instruction['require'])) {

                    $require = function ($require) {
                        require_once $require;
                    };
                    $require($instruction['require']);

                } else {

                    throw new \Exception('Builder: Отсутствует файл ' . $instruction['require']);

                }

            }

            self::$log[] = PHP_EOL . 'new ' . var_export($instruction, true) . '(...)';
            $instruction['class'] = '\\' . $instruction['class'];
            $Object = new $instruction['class'](...self::makeAll($instruction['construct'] ?? []));

            if (isset($instruction['property'])) {

                foreach ($instruction['property'] as $property => $pInstruction) {

                    $Object->{$property} = self::make($pInstruction);

                }

            }

            if (isset($instruction['method'])) {

                foreach ($instruction['method'] as $method => $mInstruction) {

                    if (!empty($mInstruction['chain'])) {

                        $Object = $Object->$method(...self::makeAll($mInstruction['argument'] ?? []));

                    } else {

                        $Object->$method(...self::makeAll($mInstruction['argument'] ?? []));

                    }

                }

            }

            if (!($instruction['new'] ?? self::$config['new']) && $alias) {
                self::$storage[$alias] = $Object;
            }

        } elseif ($instruction['clone'] ?? self::$config['clone']) {

            $Object = clone self::$storage[$alias];

        } else {

            $Object = self::$storage[$alias];

        }

        return $Object;

    }

    /**
     * Возвращает объект
     *
     * @param array $instruction
     * @param null $alias
     * @return object
     */
    protected static function makeObject (array $instruction, $alias = null) {

        if (isset(self::$storage[$alias])) {

            $object = ($instruction['clone'] ?? self::$config['clone']) ? clone self::$storage[$alias] : self::$storage[$alias];

        } else {

            $object = (object)$instruction['object'];
            if (($instruction['clone'] ?? self::$config['clone']) && $alias) {
                self::$storage[$alias] = $object;
            }

        }

        return $object;

    }

    /**
     * Возвращает результат функции
     *
     * @param array $instruction
     * @return mixed
     * @throws \Exception
     */
    protected static function makeCallback (array $instruction) {

        return $instruction['callback']->__invoke(...self::makeAll($instruction['argument'] ?? []));

    }

    /**
     * Возвращает массив
     *
     * @param array $instruction
     * @return array
     */
    protected static function makeArray (array $instruction) : array {

        return (array)$instruction['array'];

    }

    /**
     * Возвращает строку
     *
     * @param array $instruction
     * @return string
     */
    protected static function makeString (array $instruction) : string {

        return (string)$instruction['string'];

    }

    /**
     * Возвращает число
     *
     * @param array $instruction
     * @return int
     */
    protected static function makeInteger (array $instruction) : int {

        return (integer)$instruction['integer'];

    }

    /**
     * Возвращает число с плавающей точкой
     *
     * @param array $instruction
     * @return float
     */
    protected static function makeFloat (array $instruction) : float {

        return (float)$instruction['float'];

    }

    /**
     * Возвращает значение псевдонима
     *
     * @param array $instruction
     * @return mixed
     * @throws \Exception
     */
    protected static function makeAlias (array $instruction) {

        if (!isset(self::$instruction[$instruction['alias']])) {
            throw new \Exception('Builder: Настройка для alias ' . $instruction['alias'] . ' не найдена' . PHP_EOL . var_export($instruction, true) . PHP_EOL . self::$log);
        }

        $alias = $instruction['alias'];
        $instruction = self::$instruction[$alias];
        if (isset($instruction['class'])) {
            return self::makeClass($instruction, $alias);
        } else {
            return self::make($instruction);
        }

    }

    /**
     * Возвращает значение
     *
     * @param array $instruction
     * @return mixed
     */
    protected static function makeData (array $instruction) {

        return current($instruction);

    }

    /**
     * Возвращает значение исполняемого файла
     *
     * @param array $instruction
     * @return mixed
     */
    protected static function makeInclude (array $instruction) {

        return is_file($instruction['include']) ? include $instruction['include'] : null;

    }

    /**
     * Собирает по инструкции
     *
     * @param array $instruction
     * @return mixed
     * @throws \Exception
     */
    protected static function makeInstruction (array $instruction) {

        return self::make($instruction['instruction']);

    }

    /**
     * Выводит ошибку
     *
     * @param array $instruction
     * @throws \Exception
     */
    protected static function makeError (array $instruction) {

        throw new \Exception('Builder: Настройка для  ' . $instruction['error'] . ' не найдена' . PHP_EOL . var_export($instruction, true) . PHP_EOL . var_export(self::$log, true));

    }

    /**
     * Проверяет инъекцию на существование
     *
     * @param string $name
     * @return bool
     */
    public static function has (string $name) : bool {

        return isset(self::$instruction[$name]);

    }

    /**
     * Дополняет набор правил для удовлетворения зависимостей
     *
     * @param array $instruction
     * @return array
     */
    public static function instruction (array $instruction) : array {

        return self::$instruction = array_merge(self::$instruction, $instruction);

    }

    /**
     * Метод для установки настроек класса
     *
     * @param array $config
     * @return array
     */
    public static function config (array $config) : array {

        return self::$config = array_merge(self::$config, $config);

    }

}