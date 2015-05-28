<?php

/**
 *  matteosister <matteog@gmail.com>
 *  Just for fun...
 *
 *  Refactored and updated by
 *  Kamil Siewruk <kamil@k3d.be>
 *
 *  Class TaggableBehavior
 */
class TaggableBehavior extends Behavior
{

    protected $parameters = array(
        'tagging_table'         => '%TABLE%_tag',
        'tagging_table_phpname' => '%PHPNAME%Tag',
        'tag_table'             => 'tag',
        'tag_table_phpname'     => 'Tag',
    );

    /** @var Table */
    protected $taggingTable;

    /** @var Table */
    protected $tagTable;

    protected $objectBuilderModifier;
    protected $queryBuilderModifier;
    protected $peerBuilderModifier;

    public function modifyTable()
    {
        $this->createTagTable();
        $this->createTaggingTable();
    }

    protected function createTagTable()
    {
        $table           = $this->getTable();
        $tagTableName    = $this->getTagTableName();
        $tagTablePhpName = $this->replaceTokens($this->getParameter('tag_table_phpname'));
        $database        = $table->getDatabase();

        if ($database->hasTable($tagTableName)) {
            $this->tagTable = $database->getTable($tagTableName);
        } else {
            $this->tagTable = $database->addTable(
                array(
                    'name'      => $tagTableName,
                    'phpName'   => $tagTablePhpName,
                    'package'   => $table->getPackage(),
                    'schema'    => $table->getSchema(),
                    'namespace' => '\\' . $table->getNamespace(),
                )
            );

            // every behavior adding a table should re-execute database behaviors
            // see bug 2188 http://www.propelorm.org/changeset/2188
            foreach ($database->getBehaviors() as $behavior) {
                $behavior->modifyDatabase();
            }
        }

        if (!$this->tagTable->hasColumn('id')) {
            $this->tagTable->addColumn(
                array(
                    'name'          => 'id',
                    'type'          => PropelTypes::INTEGER,
                    'primaryKey'    => 'true',
                    'autoIncrement' => 'true',
                )
            );
        }

        if (!$this->tagTable->hasColumn('name')) {
            $this->tagTable->addColumn(
                array(
                    'name'          => 'name',
                    'type'          => PropelTypes::VARCHAR,
                    'size'          => '60',
                    'primaryString' => 'true'
                )
            );
        }

    }

    protected function createTaggingTable()
    {
        $table    = $this->getTable();
        $database = $table->getDatabase();
        $pks      = $this->getTable()->getPrimaryKey();
        if (count($pks) > 1) {
            throw new EngineException('The Taggable behavior does not support tables with composite primary keys');
        }
        $taggingTableName = $this->getTaggingTableName();

        if ($database->hasTable($taggingTableName)) {
            $this->taggingTable = $database->getTable($taggingTableName);
        } else {
            $this->taggingTable = $database->addTable(
                array(
                    'name'      => $taggingTableName,
                    'phpName'   => $this->replaceTokens($this->getParameter('tagging_table_phpname')),
                    'package'   => $table->getPackage(),
                    'schema'    => $table->getSchema(),
                    'namespace' => '\\' . $table->getNamespace(),
                )
            );

            // every behavior adding a table should re-execute database behaviors
            // see bug 2188 http://www.propelorm.org/changeset/2188
            foreach ($database->getBehaviors() as $behavior) {
                $behavior->modifyDatabase();
            }
        }

        if ($this->taggingTable->hasColumn('tag_id')) {
            $tagFkColumn = $this->taggingTable->getColumn('tag_id');
        } else {
            $tagFkColumn = $this->taggingTable->addColumn(
                array(
                    'name'       => 'tag_id',
                    'type'       => PropelTypes::INTEGER,
                    'primaryKey' => 'true'
                )
            );
        }

        if ($this->taggingTable->hasColumn($table->getName() . '_id')) {
            $objFkColumn = $this->taggingTable->getColumn($table->getName() . '_id');
        } else {
            $objFkColumn = $this->taggingTable->addColumn(
                array(
                    'name'       => $table->getName() . '_id',
                    'type'       => PropelTypes::INTEGER,
                    'primaryKey' => 'true'
                )
            );
        }

        $this->taggingTable->setIsCrossRef(true);

        $fkTag = new ForeignKey();
        $fkTag->setForeignTableCommonName($this->tagTable->getCommonName());
        $fkTag->setForeignSchemaName($this->tagTable->getSchema());
        $fkTag->setOnDelete(ForeignKey::CASCADE);
        $fkTag->setOnUpdate(ForeignKey::CASCADE);

        $tagColumn = $this->tagTable->getColumn('id');
        $fkTag->addReference($tagFkColumn->getName(), $tagColumn->getName());
        $this->taggingTable->addForeignKey($fkTag);

        $fkObj = new ForeignKey();
        $fkObj->setForeignTableCommonName($this->getTable()->getCommonName());
        $fkObj->setForeignSchemaName($this->getTable()->getSchema());
        $fkObj->setOnDelete(ForeignKey::CASCADE);
        $fkObj->setOnUpdate(ForeignKey::CASCADE);
        foreach ($pks as $column) {
            $fkObj->addReference($objFkColumn->getName(), $column->getName());
        }
        $this->taggingTable->addForeignKey($fkObj);

    }

    /**
     * Adds methods to the object
     */
    public function objectMethods($builder)
    {
        $this->builder = $builder;

        $script = '';

        $this->addAddTagsMethod($script);
        $this->addRemoveTagMethod($script);

        return $script;
    }

    private function addAddTagsMethod(&$script)
    {

        $script = "

/**
 * Adds Tags
 *
 * @param           \$tags
 * @param PropelPDO \$con
 * @return \$this
 */
public function addTags(\$tags, PropelPDO \$con = null)
{
    if (is_string(\$tags)) {
        \$tagNames = explode(',',\$tags);

        /** @var {$this->tagTable->getPhpName()}[]|\\PropelObjectCollection \$tags */
        \$tags = {$this->tagTable->getPhpName()}Query::create()
            ->filterByName(\$tagNames)
            ->find(\$con);

        \$existingTags = [];
        foreach (\$tags as \$t) {
            \$existingTags[] = \$t->getName();
        }

        foreach (array_diff(\$tagNames, \$existingTags) as \$t) {
            \$tag = (new {$this->tagTable->getPhpName()})->setName(\$t);

            \$newTags[] = \$tag;
        }
    }

    \$currentTags = \$this->get{$this->taggingTable->getPhpName()}s(null, \$con);

    foreach (\$tags as \$tag) {
        if (!\$currentTags->contains(\$tag)) {
            \$this->doAdd{$this->tagTable->getPhpName()}(\$tag);
        }
    }

    return \$this;
}
        ";
    }


    private function addRemoveTagMethod(&$script)
    {
        $script .= "
/**
 * @param           \$tags
 * @param PropelPDO \$con
 * @return \$this
 */
public function removeTags(\$tags, PropelPDO \$con = null)
{
    if (is_string(\$tags)) {
        \$tagNames = explode(',', \$tags);

        /** @var {$this->tagTable->getPhpName()}[]|\\PropelObjectCollection \$tags */
        \$tags = {$this->tagTable->getPhpName()}Query::create()
            ->filterByName(\$tagNames)
            ->find(\$con);
    }

    foreach (\$tags as \$tag) {
        \$this->remove{$this->tagTable->getPhpName()}(\$tag);
    }

    return \$this;
}

/**
 * Remove all tags
 *
 * @param      PropelPDO \$con optional connection object
 */
public function removeAllTags(PropelPDO \$con = null)
{
    // Get all tags for this object
    \$tags = \$this->get{$this->taggingTable->getPhpName()}s(\$con);
    if (null !== \$tags) {
        \$tags->delete();
    }
}
		";
    }

    /**
     * Adds method to the query object
     */
    public function queryMethods($builder)
    {
        $this->builder = $builder;
        $script        = '';

        $this->addFilterByTagName($script);

        return $script;
    }

    protected function addFilterByTagName(&$script)
    {
        $script .= "
/**
* Filter the query on the tag name
*
* @param     string \$tagName A single tag name
*
* @return    " . $this->builder->getStubQueryBuilder()->getClassname() . " The current query, for fluid interface
*/
public function filterByTagName(\$tagName)
{
	return \$this->use" . $this->taggingTable->getPhpName() . "Query()->useTagQuery()->filterByName(\$tagName)->endUse()->endUse();
}
public function filterByTagAndCategory(\$tagName, \$category_id)
{
	return \$this->use" . $this->taggingTable->getPhpName() . "Query()->useTagQuery()->filterByName(\$tagName)->filterByCategoryId(\$category_id)->endUse()->endUse();
}
		";
    }


    protected function getTagTableName()
    {
        return $this->replaceTokens($this->getParameter('tag_table'));
    }

    protected function getTagCategoryTableName()
    {
        return $this->getParameter('tag_category_table');
    }

    protected function getTaggingTableName()
    {
        return $this->replaceTokens($this->getParameter('tagging_table'));
    }

    public function replaceTokens($string)
    {
        $table = $this->getTable();

        return strtr(
            $string,
            array(
                '%TABLE%'   => $table->getName(),
                '%PHPNAME%' => $table->getPhpName(),
            )
        );
    }


    public function objectFilter(&$script)
    {
        $s      = <<<EOF

	if (empty(\$tags)) {
		\$this->removeAllTags(\$con);
		return;
	}

	if (is_string(\$tags)) {
		\$tagNames = explode(',',\$tags);

		\$tags = TagQuery::create()
		->filterByName(\$tagNames)
		->find(\$con);

		\$existingTags = array();
		foreach (\$tags as \$t) \$existingTags[] = \$t->getName();
		foreach (array_diff(\$tagNames, \$existingTags) as \$t) {
			\$tag=new Tag();
			\$tag->setName(\$t);
			\$tags->append(\$tag);
		}
	}
EOF;
        $script = preg_replace('/(public function setTags\()PropelCollection ([^{]*{)/', '$1$2' . $s, $script, 1);
    }

}

