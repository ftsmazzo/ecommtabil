<?php

namespace App\Core;

/**
 * Classe Collection
 * Uma classe utilitária flexível para manipulação e gerenciamento de coleções (arrays).
 */
class Collection
{
    /**
     * @var array A coleção de itens.
     */
    private $items;
    private $sorts = [
        'asc' => SORT_ASC,
        'desc' => SORT_DESC,
    ];

    /**
     * Construtor da classe.
     * Inicializa a coleção com uma lista opcional de itens.
     *
     * @param array|null $haystack Um array inicial de itens.
     */
    public function __construct($haystack = [])
    {
        $this->setHaystack($haystack);  // Chama a validação e inicializa a coleção
    }

    /**
     * Valida e define a coleção.
     *
     * @param mixed $haystack A coleção de dados a ser validada.
     * @return void
     */
    public function setHaystack($haystack): self
    {
        // Verifica se o $haystack é um array ou um objeto
        if (is_array($haystack)) {
            // Se já for um array, apenas atribui
            $this->items = $haystack;
        } elseif (is_object($haystack)) {
            // Se for um objeto, tenta convertê-lo para um array
            $this->items = (array) $haystack;
        } else {
            // Se não for nem array nem objeto, lança uma exceção
            throw new InvalidArgumentException("Haystack deve ser um array ou um objeto.");
        }

        return $this;
    }

    /**
     * Retorna todos os itens da coleção.
     *
     * @return array Os itens atuais da coleção.
     */
    public function all(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Converte a coleção para uma representação JSON.
     *
     * @return string A coleção em formato JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->items);
    }

    /**
     * Retorna o primeiro item da coleção.
     *
     * @param array|null $haystack Um array opcional para verificar.
     * @return mixed O primeiro item da coleção ou null se a coleção estiver vazia.
     */
    public function first($haystack = null)
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Retorna o primeiro item ou null se não houver nenhum item
        return isset($this->items[0]) ? $this->items[0] : null;
    }

    /**
     * Retorna o último item da coleção.
     *
     * @param array|null $haystack Um array opcional para verificar.
     * @return mixed O último item da coleção ou null se a coleção estiver vazia.
     */
    public function last($haystack = null)
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Usa a função nativa `end()` do PHP para obter o último item
        return end($this->items) ?: null;
    }

    /**
     * Alias para `last()`, retorna o último item da coleção.
     *
     * @param array|null $haystack Um array opcional para verificar.
     * @return mixed O último item da coleção ou null se a coleção estiver vazia.
     */
    public function end($haystack = null)
    {
        return $this->last($haystack);
    }

    /**
     * Reverte a ordem dos itens na coleção.
     *
     * @return self Retorna a instância para encadeamento.
     */
    public function reverse(): self
    {
        $this->items = array_reverse($this->items);
        return $this;
    }

    /**
     * Verifica se todos os itens da coleção estão vazios.
     *
     * @param array|null $haystack Um array opcional para verificar.
     * @return bool Retorna verdadeiro se todos os itens estiverem vazios; caso contrário, falso.
     */
    public function emptyAll($haystack = null): bool
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Verifica se todos os itens estão vazios
        return !array_filter($this->items, fn($field) => !empty($field));
    }

    /**
     * Verifica se algum item da coleção está vazio.
     *
     * @param array|null $haystack Um array opcional para verificar.
     * @return bool Retorna verdadeiro se algum item estiver vazio; caso contrário, falso.
     */
    public function emptyAny($haystack = null): bool
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Verifica se algum item está vazio
        return in_array(true, array_map(fn($field) => empty($field), $this->items), true);
    }

    /**
     * Alias para emptyAny(), Verifica se algum item da coleção está vazio.
     *
     * @param array|null $haystack Um array opcional para verificar.
     * @return bool Retorna verdadeiro se algum item estiver vazio; caso contrário, falso.
     */
    public function empty($haystack = null): bool
    {
        // Verifica se algum item está vazio
        return $this->emptyAny($haystack);
    }

    /**
     * Agrupa os itens por valor e conta a frequência de cada valor.
     *
     * @param array|null $haystack Um array opcional para agrupar e contar.
     * @return array Um array associativo onde as chaves são valores e os valores são contagens.
     */
    public function agroupAndCount($haystack = null): array
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $result = [];
        foreach ($this->items as $key) {
            $index = is_scalar($key) ? $key : json_encode($key);
            $result[$index] = ($result[$index] ?? 0) + 1;
        }
        return $result;
    }

    /**
     * Agrupa os itens da coleção por uma chave específica.
     *
     * @param string $key A chave pela qual agrupar os itens.
     * @param array|null $haystack Um array opcional para agrupar.
     * @return self Retorna a instância atual para encadeamento.
     */
    public function groupByKey($key, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $new = [];
        foreach ($this->items as $a) {
            $a = (array)$a;
            $new[$a[$key]][] = $a;
        }
        $this->items = $new;
        return $this;
    }

    /**
     * Extrai os valores de uma chave específica da coleção.
     *
     * @param string $key A chave cujos valores serão extraídos.
     * @param bool $unique Se verdadeiro, retorna apenas valores únicos.
     * @param array|null $haystack Um array opcional para processar.
     * @return self Retorna a instância atual para encadeamento.
     */
    public function filterByKey($key, $unique = false, $allow_empty = false, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $new = [];
        foreach ($this->items as $a) {
            $a = (array)$a; // Converte o item para array caso seja um objeto

            // Verifica se a chave existe e se a chave não está vazia (caso allow_empty seja false)
            if (array_key_exists($key, $a) && ($allow_empty || !empty($a[$key]))) {
                $new[] = $a[$key]; // Adiciona o valor à nova coleção
            }
        }

        // Aplica o array_unique se for necessário
        $this->items = $unique ? array_unique($new) : $new;

        return $this;
    }


    /**
     * Alias para filterByKey(), Extrai os valores de uma chave específica da coleção.
     *
     * @param string $key A chave cujos valores serão extraídos.
     * @param bool $unique Se verdadeiro, retorna apenas valores únicos.
     * @param array|null $haystack Um array opcional para processar.
     * @return self Retorna a instância atual para encadeamento.
     */
    public function substract($key, $unique = false, $allow_empty = false, $haystack = null): self
    {
        return $this->filterBykey($key, $unique, $allow_empty, $haystack);
    }

    /**
     * Limita o número de itens retornados pela coleção.
     *
     * @param int $limit O número máximo de itens a serem retornados.
     * @param array|null $haystack Um array opcional para limitar.
     * @return self Retorna a instância atual para encadeamento.
     */
    public function limit($limit, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $this->items = array_slice($this->items, 0, $limit);
        return $this;
    }

    /**
     * Filtra os itens da coleção com base em múltiplos valores de chave.
     *
     * @param array $filters Um array associativo de filtros (ex: ["type" => "subscription", "custom_id" => "x"]).
     * @param array|null $haystack A coleção de itens a ser filtrada.
     * @return self Retorna a instância atual da coleção para encadeamento de métodos.
     */
    public function filterByValue(array $filters, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Filtra os itens com base em todos os filtros fornecidos
        $this->items = array_filter($this->items, function ($item) use ($filters) {
            foreach ($filters as $key => $value) {
                // Verificar se o item é um objeto ou um array
                if (is_object($item)) {
                    if (!isset($item->$key) || $item->$key !== $value) {
                        return false; // Se qualquer filtro não corresponder, o item será descartado
                    }
                } else {
                    if (!isset($item[$key]) || $item[$key] !== $value) {
                        return false; // Se qualquer filtro não corresponder, o item será descartado
                    }
                }
            }
            return true; // Se todos os filtros corresponderem, o item será mantido
        });

        // Reindexa a coleção para garantir chaves numéricas contínuas
        $this->items = array_values($this->items);

        return $this; // Retorna a instância para encadeamento
    }

    /**
     * Ordena os itens da coleção com base em uma chave específica.
     *
     * @param string $key A chave que será usada para ordenar os itens.
     * @param string $order A direção da ordenação. Pode ser "asc" para ascendente ou "desc" para descendente.
     * @param array|null $haystack A coleção de itens a ser ordenada.
     * @return self Retorna a instância atual da coleção para encadeamento de métodos.
     */
    public function orderBy($key, $order = "asc", $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        usort($this->items, function ($a, $b) use ($key, $order) {
            // Verifica se $a e $b são objetos ou arrays e acessa as chaves adequadamente
            $aValue = (is_object($a) && isset($a->$key)) ? $a->$key : (is_array($a) ? $a[$key] : null);
            $bValue = (is_object($b) && isset($b->$key)) ? $b->$key : (is_array($b) ? $b[$key] : null);

            // Compara os valores
            $result = $aValue <=> $bValue;
            return $order === "asc" ? $result : -$result;
        });

        return $this;
    }

    /**
     * Realiza uma busca na coleção por um valor específico em uma chave.
     *
     * @param string $field O campo a ser pesquisado.
     * @param mixed $value O valor a ser buscado.
     * @param array|null $haystack Um array opcional para buscar.
     * @return int|null A posição do item encontrado ou null se não encontrado.
     */
    public function search($field, $value, $haystack = null): ?int
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        foreach ($this->items as $key => $node) {
            $node = (array)$node;
            if ($node[$field] === $value) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Ordena múltiplas colunas na coleção.
     *
     * @param array $cols Array de colunas e suas ordens (ex.: ['name' => 'asc', 'age' => 'desc']).
     * @param mixed $haystack Coleção a ser ordenada (opcional).
     * @return self Retorna a instância para encadeamento.
     */
    public function multiSort(array $cols, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        foreach ($this->items as $k => $obj) {
            $this->items[$k] = (array)$obj; // Converte objetos para arrays para ordenação
        }

        $colarr = [];
        foreach ($cols as $col => $order) {
            $colarr[$col] = [];
            foreach ($this->items as $k => $row) {
                $colarr[$col]['_' . $k] = strtolower($row[$col]);
            }
        }

        // Executa a ordenação dinâmica
        $eval = 'array_multisort(';
        foreach ($cols as $col => $order) {
            $eval .= '$colarr[\'' . $col . '\'],' . $this->sorts[strtolower($order)] . ',';
        }
        $eval = substr($eval, 0, -1) . ');';

        eval($eval);

        $new = [];
        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                $k = substr($k, 1);
                if (!isset($new[$k])) {
                    $new[$k] = $this->items[$k];
                }
                $new[$k][$col] = $this->items[$k][$col];
            }
        }

        $this->items = array_values($new);
        return $this;
    }

    /**
     * Verifica se todos os itens estão presentes no array de busca.
     *
     * @param array $needles Itens a serem verificados.
     * @param mixed $haystack Coleção a ser verificada (opcional).
     * @return bool Retorna true se todos os itens forem encontrados.
     */
    public function inArrayAll($needles, $haystack = null): bool
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Verifica se todos os itens estão presentes na coleção
        return empty(array_diff($needles, array_map(function ($item) {
            return (is_object($item) ? get_object_vars($item) : $item);
        }, $this->items)));
    }

    /**
     * Verifica se algum item está presente no array de busca.
     *
     * @param array $needles Itens a serem verificados.
     * @param mixed $haystack Coleção a ser verificada (opcional).
     * @return bool Retorna true se algum item for encontrado.
     */
    public function inArrayAny($needles, $haystack = null): bool
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Verifica se algum item está presente na coleção
        return !empty(array_intersect($needles, array_map(function ($item) {
            return (is_object($item) ? get_object_vars($item) : $item);
        }, $this->items)));
    }

    /**
     * Agrupa os itens da coleção por uma chave.
     *
     * @param string $key Chave para o agrupamento.
     * @param mixed $haystack Coleção a ser agrupada.
     * @return array Retorna um array agrupado por chave.
     */
    public function arrayGroupBy($key, $haystack = null): array
    {
        $result = [];

        // Verifica se foi fornecido um array customizado para busca
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        foreach ($this->items as $val) {
            // Converte o item para array, caso seja um objeto
            $val = (array)$val;

            // Verifica se a chave existe no item
            if (array_key_exists($key, $val)) {
                $result[$val[$key]][] = $val; // Agrupa por valor da chave
            } else {
                $result[""][] = $val; // Caso não tenha a chave, agrupa na chave vazia
            }
        }

        return $result;
    }

    /**
     * Remove itens duplicados baseados em uma chave e pode reorganizar os itens.
     *
     * @param string $key Chave usada para verificar duplicados.
     * @param bool $reorder Se verdadeiro, os itens serão reorganizados.
     * @param mixed $haystack Coleção a ser modificada (opcional).
     * @return self Retorna a instância para encadeamento.
     */
    public function arrayUniqueMult($key, $reorder = true, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $key_array = [];
        $temp_array = [];
        foreach ($this->items as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[] = $val[$key];
                if ($reorder) {
                    $temp_array[] = $val;
                } else {
                    $temp_array[] = $val;
                }
            }
        }

        $this->items = $temp_array;
        return $this;
    }

    /**
     * Extrai um campo específico de todos os itens da coleção.
     *
     * @param string $key O campo a ser extraído.
     * @param mixed $haystack Coleção a ser modificada (opcional).
     * @return array Retorna um array com os valores extraídos ou null quando o campo não existir.
     */
    public function pluck($key, $haystack = null): array
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $result = [];
        foreach ($this->items as $item) {
            $item = (array)$item;
            $result[] = $item[$key] ?? null;
        }

        return $result;
    }

    /**
     * Divide a coleção em pedaços menores.
     *
     * @param int $size O tamanho de cada "pedaço".
     * @param mixed $haystack Coleção a ser dividida (opcional).
     * @return array Retorna um array de subcoleções, cada uma com o tamanho especificado.
     */
    public function chunk(int $size, $haystack = null): array
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        return array_chunk($this->items, $size);
    }

    /**
     * Achata a coleção para um único nível.
     *
     * @param mixed $haystack Coleção a ser achata (opcional).
     * @return self Retorna a instância da coleção.
     */
    public function flatten($haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $this->items = array_merge(...$this->items);
        return $this;
    }

    /**
     * Soma os valores de um campo específico da coleção.
     *
     * @param string $key O campo a ser somado.
     * @param mixed $haystack Coleção a ser modificada (opcional).
     * @return float Retorna a soma dos valores.
     */
    public function sum($key, $haystack = null): float
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        return array_reduce($this->items, function($carry, $item) use ($key) {
            $item = (array)$item;
            return $carry + ($item[$key] ?? 0);
        }, 0);
    }

    /**
     * Converte o case das chaves especificadas para upper, lower ou camel case.
     *
     * @param array|string $keys Uma ou mais chaves a serem convertidas.
     * @param string $case O tipo de case a ser aplicado ('upper', 'lower', 'camel').
     * @param mixed $haystack Coleção a ser modificada (opcional).
     * @return self Retorna a instância da coleção.
     */
    public function toCase($keys, $case = "upper", $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        // Garante que $keys seja sempre um array
        $keys = (array)$keys;

        foreach ($this->items as &$item) {
            $item = (array)$item;

            foreach ($keys as $key) {
                if (isset($item[$key])) {
                    switch ($case) {
                        case 'upper':
                            $item[$key] = strtoupper($item[$key]);
                            break;
                        case 'lower':
                            $item[$key] = strtolower($item[$key]);
                            break;
                        case 'camel':
                            $item[$key] = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $item[$key]))));
                            break;
                        default:
                            // Se o case for inválido, não faz nada
                            break;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Remove espaços em branco de valores específicos nas chaves fornecidas.
     *
     * @param array $keys Um array de chaves para as quais os valores terão espaços em branco removidos.
     * @param mixed $haystack Coleção a ser modificada (opcional).
     * @return self Retorna a instância da coleção.
     */
    public function trim(array $keys, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        foreach ($this->items as &$item) {
            $item = (array)$item;

            foreach ($keys as $key) {
                if (isset($item[$key]) && is_string($item[$key])) {
                    $item[$key] = trim($item[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Encontra o primeiro item na coleção com base em uma chave e valor.
     *
     * Este método percorre a coleção e retorna o primeiro item que possui o valor
     * correspondente à chave fornecida. Se não houver nenhum item com esse valor,
     * o método retornará `null`.
     *
     * @param string $key A chave a ser procurada.
     * @param mixed $value O valor a ser encontrado.
     * @param mixed $haystack Coleção a ser verificada (opcional).
     * @return mixed O item encontrado ou `null` caso não encontrado.
     */
    public function find($key, $value, $haystack = null)
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        foreach ($this->items as $item) {
            if (isset($item[$key]) && $item[$key] == $value) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Remove um item da coleção com base em uma chave e valor.
     *
     * Este método percorre a coleção e remove todos os itens que possuam a chave
     * com o valor fornecido. A coleção é reindexada após a remoção dos itens.
     *
     * @param string $key A chave a ser verificada.
     * @param mixed $value O valor a ser comparado.
     * @param mixed $haystack Coleção a ser modificada (opcional).
     * @return self Retorna a instância da coleção para encadeamento.
     */
    public function remove($key, $value, $haystack = null): self
    {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $this->items = array_filter($this->items, function($item) use ($key, $value) {
            return !(isset($item[$key]) && $item[$key] == $value);
        });

        // Reindexa os itens
        $this->items = array_values($this->items);
        return $this;
    }

    /**
     * Extrai um atributo de uma coleção de Models ou objetos.
     *
     * @param string $key Atributo a ser extraído
     * @param mixed  $haystack Coleção a ser processada (opcional)
     * @param bool   $unique Retorna valores únicos
     * @param bool   $allowNull Mantém valores null
     * @return array
     */
    public function pluckAttribute(
        string $key,
        $haystack = null,
        bool $unique = false,
        bool $allowNull = false
    ): array {
        if ($haystack) {
            $this->setHaystack($haystack);
        }

        $result = [];

        foreach ($this->items as $item) {

            $value = null;

            // Model (seu ORM)
            if (is_object($item) && method_exists($item, 'getAttribute')) {
                $value = $item->getAttribute($key);

            // stdClass ou objeto simples
            } elseif (is_object($item)) {
                $value = $item->$key ?? null;

            // array
            } elseif (is_array($item)) {
                $value = $item[$key] ?? null;
            }

            if ($value === null && !$allowNull) {
                continue;
            }

            $result[] = $value;
        }

        return $unique ? array_values(array_unique($result)) : $result;
    }



}
