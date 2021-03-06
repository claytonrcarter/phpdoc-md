<?php namespace Clean\PhpDocMd;

use phpDocumentor\Reflection\DocBlock;
use ReflectionClass;
use ReflectionMethod;

class ClassParser
{
    /**
     * @var ReflectionClass
     */
    private $reflection;
    private $phpAtlas;

    public function __construct(ReflectionClass $class)
    {
        $this->reflection = $class;
        $this->phpatlas = require(__DIR__.'/phpatlas.php');

    }

    public function getClassDescription()
    {
        $docblock = new DocBlock($this->reflection);
        return (object)[
            'short' => (string)$docblock->getShortDescription(),
            'long' => (string)$docblock->getLongDescription(),
        ];
    }

    public function getParentClassName()
    {
        return ($p = $this->reflection->getParentClass()) ? $p->getName() : null;
    }

    public function getInterfaces()
    {
        return $this->reflection->getInterfaceNames();
    }

    public function getMethodsDetails()
    {
        $methods = [];
        $parentClassMethods = $this->getInheritedMethods();

        foreach ($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (isset($parentClassMethods[$method->getName()])) {
                continue;
            }
            $methods[$method->getName()] = $this->getMethodDetails($method);
        }

        return $methods;
    }

    private function getMethodDetails($method)
    {
        $docblock = new DocBlock($method);

        if ($docblock->getShortDescription()) {
            $data = [
                'shortDescription' => $docblock->getShortDescription(),
                'longDescription' => $docblock->getLongDescription(),
                'argumentsList' => $this->retrieveParams($docblock->getTagsByName('param')),
                'argumentsDescription' => $this->retrieveParamsDescription($docblock->getTagsByName('param')),
                'returnValue' => $this->retrieveTagData($docblock->getTagsByName('return')),
                'throwsExceptions' => $this->retrieveTagData($docblock->getTagsByName('throws')),
                'visibility' =>  join(
                    '',
                    [
                        $method->isFinal() ? 'final ' : '',
                        'public',
                        $method->isStatic() ? ' static' : '',
                    ]
                ),
            ];
        } else {
            $data = [];
            $key = sprintf("%s::%s", $method->class, $method->name);
            if (array_key_exists($key, $this->phpatlas)) {
                $data['shortDescription'] = $this->phpatlas[$key];
                $data['doclink'] = $this->getPHPDocLink($method);
            }
        }
        return (object)$data;
    }

    public function getInheritedMethods()
    {
        $methods = [];
        $parentClass = $this->reflection->getParentClass();
        if ($parentClass) {
            foreach ($parentClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $methods[$method->getName()] = $this->getMethodDetails($method);
            }
        }
        ksort($methods);
        return $methods;
    }

    private function retrieveTagData(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = (object)[
                'desc' => $param->getDescription(),
                'type' => $param->getTypes(),
            ];
        }
        return $data;
    }

    private function retrieveParams(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = sprintf("%s %s", join('|', $param->getTypes()), $param->getVariableName());
        }
        return $data;
    }

    private function retrieveParamsDescription(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = (object)[
                'name' => $param->getVariableName(),
                'desc' => $param->getDescription(),
                'type' => $param->getTypes(),
            ];
        }
        return $data;
    }

    private function getPHPDocLink($method) {
        return strtolower(sprintf('https://secure.php.net/manual/en/%s.%s.php', $method->class, $method->name));
    }
}
