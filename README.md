TaggableBehavior
====================

Installation
------------

Download TaggableBehavior.php and put it somewhere.

``` ini
propel.behavior.taggable.class = path.to.taggable.behavior
```

If you are using composer then just add:
```js
{
    "require": {
        "k3dbe/propel-taggable-behavior": "*"
    }
}
```

The ini-configuration would be
``` ini
propel.behavior.taggable.class = vendor.k3dbe.src.propel-taggable-behavior.src.TaggableBehavior
```

Usage
-----

Behavior creates two persistent tables:
* tag (id, name)
* %table%_tag

Add to schema.xml:

``` xml
<behavior name="taggable" />
```

Behavior will add several methods to the Model:

``` php
public function addTags($tags, PropelPDO $con = null)
public function removeTags($tags, PropelPDO $con = null)
public function addTag(Tag $tag)
public function removeTag(Tag $tag)
public function removeAllTags(PropelPDO $con = null)
```
