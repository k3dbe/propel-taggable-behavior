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
        'tagging_table'              => '%TABLE%_tagging',
        'tagging_table_phpname'      => '%PHPNAME%Tagging',
        'tag_table'                  => 'tags',
        'tag_table_phpname'          => 'Tag',
        'enable_categories'          => false,
        'tag_category_table'         => 'tags_categories',
        'tag_category_table_phpname' => 'TagCategory',
    );

    protected $taggingTable,
        $tagTable,
        $tagCategoryTable,
        $objectBuilderModifier,
        $queryBuilderModifier,
        $peerBuilderModifier;

    public function modifyTable()
    {
        if (true === $this->getParameter('enable_categories')) {
            $this->createTagCategoryTable();
        }

        $this->createTagTable();
        $this->createTaggingTable();
    }

    protected function createTagCategoryTable()
    {
        $table    = $this->getTable();
        $database = $table->getDatabase();

        $tagCategoryTableName    = $this->getTagCategoryTableName();
        $tagCategoryTablePhpName = $this->replaceTokens($this->getParameter('tag_category_table_phpname'));

        if ($database->hasTable($tagCategoryTableName)) {
            $this->tagCategoryTable = $database->getTable($tagCategoryTableName);
        } else {
            $this->tagCategoryTable = $database->addTable(
                array(
                    'name'      => $tagCategoryTableName,
                    'phpName'   => $tagCategoryTablePhpName,
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

        if (!$this->tagCategoryTable->hasColumn('id')) {
            $this->tagCategoryTable->addColumn(
                array(
                    'name'          => 'id',
                    'type'          => PropelTypes::INTEGER,
                    'primaryKey'    => 'true',
                    'autoIncrement' => 'true',
                )
            );
        }

        if (!$this->tagCategoryTable->hasColumn('name')) {
            $this->tagCategoryTable->addColumn(
                array(
                    'name'          => 'name',
                    'type'          => PropelTypes::VARCHAR,
                    'size'          => '60',
                    'primaryString' => 'true'
                )
            );
        }
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

        if (true === $this->getParameter('enable_categories')) {

            if ($this->tagTable->hasColumn('category_id')) {
                $categoryFkColumn = $this->tagTable->getColumn('category_id');
            } else {
                $categoryFkColumn = $this->tagTable->addColumn(
                    array(
                        'name'     => 'category_id',
                        'type'     => PropelTypes::INTEGER,
                        'required' => false,
                    )
                );
            }

            $fkTagCategory = new ForeignKey();
            $fkTagCategory->setPhpName('Category');
            $fkTagCategory->setForeignTableCommonName($this->tagCategoryTable->getCommonName());
            $fkTagCategory->setForeignSchemaName($this->tagCategoryTable->getSchema());
            $fkTagCategory->setOnDelete(ForeignKey::CASCADE);
            $fkTagCategory->setOnUpdate(ForeignKey::CASCADE);
            $pks = $this->getTable()->getPrimaryKey();
            foreach ($pks as $column) {
                $fkTagCategory->addReference($categoryFkColumn->getName(), $column->getName());
            }
            $this->tagTable->addForeignKey($fkTagCategory);
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
        foreach ($pks as $column) {
            $fkTag->addReference($tagFkColumn->getName(), $column->getName());
        }
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
        $table = $this->getTable();
        $script .= "

/**
* Add tags
* @param   array|string    \$tags A string for a single tag or an array of strings for multiple tags
* @param   PropelPDO       \$con optional connection object
*/
public function addTags(\$tags, \$category_id = null, PropelPDO \$con = null) {
	\$arrTags = is_string(\$tags) ? explode(',', \$tags) : \$tags;
	// Remove duplicate tags. 
	\$arrTags = array_intersect_key(\$arrTags, array_unique(array_map('strtolower', \$arrTags)));
	foreach (\$arrTags as \$tag) {
		\$tag = trim(\$tag);
		if (\$tag == \"\") continue;
		\$theTag = {$this->tagTable->getPhpName()}Query::create()
			->filterByName(\$tag)";

        if (true === $this->getParameter('enable_categories')) {
            $script .= "
            ->_if(!is_null(\$category_id))
				->filterByCategoryId(\$category_id)
			->_endIf()";
        }
        $script .= "
			->findOne();

		// if the tag do not already exists
		if (null === \$theTag) {
			// create the tag
			\$theTag = new {$this->tagTable->getPhpName()}();
			\$theTag->setName(\$tag);";
        if (true === $this->getParameter('enable_categories')) {
            $script .= "
			\$theTag->setCategoryId(\$category_id);";
        }
        $script .= "
			\$theTag->save(\$con);
		}
		  // Add the tag **only** if not already associated 
		\$found = false;
		\$coll = \$this->getTags(null, \$con);
		foreach (\$coll as \$t) {
		    if ((\$t->getId() == \$theTag->getId()) && (!\$category_id || (\$category_id == \$t->getCategoryId()))) {
		        \$found = true;
		        break;  
		    }
		}
		if (!\$found) {
		    \$this->addTag(\$theTag);
		}
	}
}

		";
    }


    private function addRemoveTagMethod(&$script)
    {
        $table = new Table();
        $table = $this->getTable();

        $script .= "
/**
* Remove a tag
* @param   array|string    \$tags A string for a single tag or an array of strings for multiple tags
*/
public function removeTags(\$tags, \$category_id = null) {
\$arrTags = is_string(\$tags) ? explode(',', \$tags) : \$tags;
	foreach (\$arrTags as \$tag) {
		\$tag = trim(\$tag);
		\$tagObj = {$this->tagTable->getPhpName()}Query::create()
			->filterByName(\$tag)
			->_if(!is_null(\$category_id))
				->filterByCategoryId(\$category_id)
			->_endIf()
			->findOne();
		if (null === \$tagObj) {
		    return;
		}
		\$taggings = \$this->get{$this->taggingTable->getPhpName()}s();
		foreach (\$taggings as \$tagging) {
			if (\$tagging->get{$this->tagTable->getPhpName()}Id() == \$tagObj->getId()) {
				\$tagging->delete();
			}
		}
	}
}
   
/**
* Remove all tags
* @param      PropelPDO \$con optional connection object
*/
public function removeAllTags(PropelPDO \$con = null) {
	// Get all tags for this object
	\$taggings = \$this->get{$this->taggingTable->getPhpName()}s(\$con);
	foreach (\$taggings as \$tagging) {
		\$tagging->delete(\$con);
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

