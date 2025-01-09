<?php

namespace Auxilium\DatabaseInteractions\MariaDB;

use Aura\SqlQuery\AbstractQuery;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryFactory;

class SQLQueryBuilderWrapper
{
    public static function SELECT(MariaDBTable $table): AbstractQuery|SelectInterface
    {
        $query_factory = new QueryFactory(db: 'mysql');
        $query = $query_factory->newSelect()
            ->from(spec: $table->value . " AS T");

        return $query;
    }
    public static function INSERT(MariaDBTable $table): AbstractQuery|InsertInterface
    {
        $query_factory = new QueryFactory(db: 'mysql');
        $query = $query_factory->newInsert()
            ->into(into: $table->value);

        return $query;
    }
}
