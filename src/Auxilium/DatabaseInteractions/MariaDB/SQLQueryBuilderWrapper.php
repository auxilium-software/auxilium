<?php

namespace Auxilium\DatabaseInteractions\MariaDB;

use Aura\SqlQuery\AbstractQuery;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryFactory;

class SQLQueryBuilderWrapper
{
    public static function SELECT(MariaDBTable $table): AbstractQuery|SelectInterface
    {
        $query_factory = new QueryFactory(db: 'mysql');
        $query = $query_factory->newSelect()
            ->from($table->value . " AS T")
        ;

        return $query;
    }
}
