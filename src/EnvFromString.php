<?php

namespace Happy;

use Dotenv\Loader\Lines;
use Dotenv\Loader\Parser;

class EnvFromString
{
    private array $values = [];

    public function __construct(string $content)
    {
        $lines = Lines::process(preg_split("/(\r\n|\n|\r)/", $content));

        foreach ($lines as $line) {
            [$name, $value] = Parser::parse($line);
            $this->values[$name] = $value->getChars();
        }
    }

    /**
     * @param string|null $key
     * @return array|string|null
     */
    public function get(string $key = null)
    {
        if (is_null($key)) {
            return $this->values;
        }

        return $this->values[$key] ?? null;
    }

}