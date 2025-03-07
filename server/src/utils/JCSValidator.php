<?php
namespace Jsonpost\utils;
use \InvalidArgumentException as InvalidArgumentException;
use \OutOfBoundsException as Exception;

class CharIterator {
    private string $s;
    private int $p = 0;

    public function __construct(string $input) {
        $this->s = $input;
    }

    public function getP(): int {
        return $this->p;
    }

    public function next(): string {
        if ($this->p >= mb_strlen($this->s, 'UTF-8')) {
            throw new Exception("StopIteration");
        }
        return mb_substr($this->s, $this->p++, 1, 'UTF-8');
    }

    public function currentContext(): string {
        $start = max(0, $this->p - 10);
        return mb_substr($this->s, $start, $this->p - $start, 'UTF-8');
    }
}

class InvalidJcsException extends Exception {
    public function __construct(CharIterator $iter) {
        $context = $iter->currentContext();
        parent::__construct("Error at position {$iter->getP()}: {$context}");
    }
}

class JCSValidator {
    public function isJcsToken(string $src) {
        $iter=new CharIterator($src);    
        $c = $iter->next();
        if ($c === '{') {
            $this->isValidObject($iter);
        } elseif ($c === '[') {
            $this->isValidArray($iter);
        } else {
            throw new InvalidJcsException($iter);
        }
    }

    private function isValidArray(CharIterator $iter) {
        $last_c = null;
        while (true) {
            $c = $iter->next();
            if ($c === '"') {
                $this->isValidString($iter);
                $c = $iter->next();
            } elseif ($c === '{') {
                $this->isValidObject($iter);
                $c = $iter->next();
            } elseif ($c === '[') {
                $this->isValidArray($iter);
                $c = $iter->next();
            } elseif (in_array($c, ['t', 'f', 'n'])) {
                $this->isValidLiteral($c, $iter);
                $c = $iter->next();
            } elseif ($c === ']') {
                if ($last_c === ',') {
                    throw new InvalidJcsException($iter);
                }
                return;
            } else {
                $c = $this->isValidNumber($c, $iter);
            }

            if ($c === ']') {
                return;
            } elseif ($c !== ',') {
                throw new InvalidJcsException($iter);
            }
            $last_c = $c;
        }
    }

    private function isValidSet(CharIterator $iter): array {
        $k = $this->isValidKey($iter);
        if ($iter->next() !== ':') {
            throw new InvalidJcsException($iter);
        }
        $c = $iter->next();
        if ($c === '"') {
            $this->isValidString($iter);
            $c = $iter->next();
        } elseif ($c === '{') {
            $this->isValidObject($iter);
            $c = $iter->next();
        } elseif ($c === '[') {
            $this->isValidArray($iter);
            $c = $iter->next();
        } elseif (in_array($c, ['t', 'f', 'n'])) {
            $this->isValidLiteral($c, $iter);
            $c = $iter->next();
        } else {
            $c = $this->isValidNumber($c, $iter);
        }
        return [$c, $k];
    }

    private function isValidObject(CharIterator $iter) {
        $last_c = null;
        $last_key = null;
        while (true) {
            $c = $iter->next();
            if ($c === '"') {
                [$c, $k] = $this->isValidSet($iter);
                if ($last_key !== null && strcmp($k, $last_key) <= 0) {
                    throw new InvalidJcsException($iter);
                }
                $last_key = $k;
            } elseif ($c === '}') {
                if ($last_c === ',') {
                    throw new InvalidJcsException($iter);
                }
                return;
            } else {
                throw new InvalidJcsException($iter);
            }
            if ($c === '}') {
                return;
            } elseif ($c !== ',') {
                throw new InvalidJcsException($iter);
            }
            $last_c = $c;
        }
    }

    private function isValidNumber(string $pre_c, CharIterator $iter): string {
        $num = 0;

        if (in_array($pre_c, ['+', '-', '.'])) {
            // 初期記号 OK
        } elseif (ctype_digit($pre_c)) {
            $num++;
        } else {
            throw new InvalidJcsException($iter);
        }

        if ($pre_c === '.') {
            $c = $pre_c;
        } else {
            do {
                $c = $iter->next();
                if (ctype_digit($c)) {
                    $num++;
                } else {
                    break;
                }
            } while (true);
        }

        if ($c === '.') {
            do {
                $c = $iter->next();
                if (ctype_digit($c)) {
                    $num++;
                } else {
                    break;
                }
            } while (true);
        }

        if ($num === 0) {
            throw new InvalidJcsException($iter);
        }

        if (strtolower($c) === 'e') {
            $num = 0;
            $c = $iter->next();
            if (in_array($c, ['+', '-'])) {
                $c = $iter->next();
            }

            if (ctype_digit($c)) {
                $num++;
                while (ctype_digit($c = $iter->next())) {
                    $num++;
                }
            } else {
                throw new InvalidJcsException($iter);
            }
        }

        if ($num === 0) {
            throw new InvalidJcsException($iter);
        }

        return $c;
    }

    private function isValidString(CharIterator $iter) {
        while (true) {
            $c = $iter->next();
            if ($c === '"') {  // 文字列終了
                return;
            } elseif ($c === '\\') {  // エスケープシーケンスの開始
                $c = $iter->next();  // エスケープされた文字
                if (!in_array($c, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'])) {
                    throw new InvalidJcsException($iter);
                }
            }
        }
    }
    
    private function isValidKey(CharIterator $iter): string {
        $k = '';
        while (true) {
            $c = $iter->next();
            if ($c === '"') {  // 文字列終了
                if (strlen($k) === 0) {
                    throw new InvalidJcsException($iter);
                }
                return $k;
            } elseif ($c === '\\') {  // エスケープシーケンスの開始
                $k .= $c;
                $c = $iter->next();  // エスケープされた文字
                if (!in_array($c, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'])) {
                    throw new InvalidJcsException($iter);
                }
                $k .= $c;
            } else {
                $k .= $c;
            }
        }
    }
    

    private function isValidLiteral(string $c, CharIterator $iter) {
        $map = [
            't' => 'rue',
            'f' => 'alse',
            'n' => 'ull'
        ];
        if (!isset($map[$c])) {
            throw new InvalidJcsException($iter);
        }
        foreach (str_split($map[$c]) as $expected) {
            if ($iter->next() !== $expected) {
                throw new InvalidJcsException($iter);
            }
        }
    }
}