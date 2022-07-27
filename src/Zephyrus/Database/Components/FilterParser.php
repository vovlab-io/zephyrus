<?php namespace Zephyrus\Database\Components;

use Zephyrus\Database\QueryBuilder\WhereClause;
use Zephyrus\Database\QueryBuilder\WhereCondition;
use Zephyrus\Network\RequestFactory;

class FilterParser
{
    public const URL_PARAMETER = 'filters';

    private WhereClause $whereClause;
    private array $allowedColumns;

    public function __construct(array $allowedColumns = [])
    {
        $this->allowedColumns = $allowedColumns;
    }

    /**
     * Parses the request parameters to build a corresponding WHERE clause. The parameters should be given following the
     * public constants:
     *
     *     example.com?filters[column:type]=content
     *
     * The columnConversion array allows specifying correspondance between request parameters and database column (if
     * developers don't want to expose database column directly in UI links). If a specified column is not allowed it
     * will be ignored. If no column type is given, the "contains" default will be considered.
     *
     * @param array $columnConversion
     * @return WhereClause
     */
    public function parse(array $columnConversion = []): WhereClause
    {
        $this->whereClause = new WhereClause();
        $request = RequestFactory::read();
        $filterColumns = $request->getParameter(self::URL_PARAMETER, []);
        foreach ($filterColumns as $columnDefinition => $content) {
            if (!str_contains($columnDefinition, ":")) {
                $columnDefinition = $columnDefinition . ':' . 'contains';
            }
            list($column, $filterType) = explode(':', $columnDefinition);
            if (!in_array($column, $this->allowedColumns)) {
                continue;
            }
            // TODO: Select between or / and from received data ...
            // TODO: Validate "content" for each type of clause (ex. date_range, number, etc.)
            // TODO: Make private method for all the matches
            match ($filterType) {
                'contains' => $this->whereClause->or(WhereCondition::like($columnConversion[$column] ?? $column, "%$content%")),
                'begins' => $this->whereClause->or(WhereCondition::like($columnConversion[$column] ?? $column, "$content%")),
                'ends' => $this->whereClause->or(WhereCondition::like($columnConversion[$column] ?? $column, "%$content")),
                'equals' => $this->whereClause->or(WhereCondition::equals($columnConversion[$column] ?? $column, $content)),
            };
        }
        return $this->whereClause;
    }
}
