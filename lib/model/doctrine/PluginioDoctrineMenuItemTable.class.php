<?php

/**
 * PluginioDoctrineMenuItemTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class PluginioDoctrineMenuItemTable extends Doctrine_Table
{

  /**
   * Creates a full ioMenuItem tree from the given $name, which is either:
   *   * The name of a root menu node
   *   * An ioMenuItem object that the menu will be sourced from
   *
   * @param  string|ioDoctrineMenuItem $name The root node to translate into an ioMenuItem
   * @return ioMenuItem
   */
  public function fetchMenu($name)
  {
    if (is_string($name))
    {
      $name = $this->fetchRootByName($name);
    }

    if (!$name)
    {
      return null;
    }

    return $name->createMenu();
  }

  /**
   * Persists an ioMenuItem tree to the database.
   *
   * Typically, you'll persist your entire menu tree. This will save the root
   * menu item as a root in Doctrine's nested set with the whole tree under it:
   *
   * $menu = new ioMenuItem('root');
   * $menu->addChild('Home', '@homepage');
   * Doctrine_Core::getTable('ioDoctrineMenuItem')->persist($menu);
   *
   * You can also persist part of a tree or persist a full menu under an
   * existing Doctrine nested set node:
   *
   * $menu->addChild('Links');
   * $menu['Links']->addChild('Sympal', 'http://www.sympalphp.org');
   * $tbl = Doctrine_Core::getTable('ioDoctrineMenuItem');
   * $node = $tbl->findOneByName('some name'); // find an existing node
   * // save the Links submenu under the above node
   * $tbl->persist($menu['Links'], $node);
   *
   * @param  ioMenuItem $menu
   * @param  ioDoctrineMenuItem $parentDoctrineMenu Optional parent node, else
   *                                                it will save as root
   * @return ioDoctrineMenuItem
   * @throws sfException
   */
  public function persist(ioMenuItem $menu, ioDoctrineMenuItem $parent = null)
  {
    // run a few sanity checks and create the root node
    if (!$parent)
    {
      // protect against people calling persist on non-root objects, which
      // would otherwise cause those items to persist as new roots
      if (!$menu->isRoot())
      {
        throw new sfException(
          'Non-root menu items as root items. Either persist the entire
          tree or pass an ioDoctrineMenuItem parent as the second argument.'
        );
      }

      // Make sure the root has a name
      if (!$menu->getName())
      {
        throw new sfException(
          'A root object cannot be persisted without a name. Call setName()
          on the root menu item to set its name'
        );
      }

      $root = $this->fetchRootByName($menu->getName());
      if (!$root)
      {
        // create a new root
        $root = new ioDoctrineMenuItem();
        $root->name = $menu->getName();
        $root->save();
        $this->getTree()->createRoot($root);
      }

      $parent = $root;
    }

    // merge in the menu data into the parent menu
     $parent->persistFromMenuArray($menu->toArray());
  }

  /**
   * Retrieves the root menu item specified by the given name
   *
   * @param  $name The value of the name field of the menu item
   * @return ioDoctrineMenuItem|null
   */
  public function fetchRootByName($name)
  {
    return $this->createQuery('m')
      ->where('m.lft = ?', 1)
      ->andWhere('m.name = ?', $name)
      ->fetchOne();
  }
  
  /**
   * Clear a tree based on a root id.  Leave the root node intact
   *
   * @param string $name - the name id to match the branch on
   * @return void
   * @author Brent Shaffer
   */
  public function clearTree($root)
  {
    $nodes = $this->createQuery()
      ->where('root_id = ?', $root['id'])
      ->andWhere('level != ?', 0)
      ->execute();

    foreach ($nodes as $node)
    {
      $node->getNode()->detach();
      $node['level'] = null;
      $node->save();
    }
  }

  /**
   * builds a tree from a nested array.
   *
   * @param string $arr 
   * @param string $orgId 
   * @return void
   * @author Brent Shaffer
   */
  public function restoreTreeFromNestedArray($arr, $name)
  {
    $root = $this->fetchRootByName($name);

    $this->clearTree($root);

    Doctrine::getTable('ioDoctrineMenuItem')->getTree()->createRoot($root);
    
    $this->restoreBranchFromNestedArray(array('id' => $root['id'], 'children' => $arr));
  }
  
  /**
   * recursive function to create a nested set from an array
   *
   * @param string $arr
   * @return void
   * @author Brent Shaffer
   */
  public function restoreBranchFromNestedArray($arr)
  {
    $parent = Doctrine::getTable('ioDoctrineMenuItem')->findOneById($arr['id']);

    if (isset($arr['children'])) 
    {
      foreach ($arr['children'] as $childArr) 
      {
        $child = Doctrine::getTable('ioDoctrineMenuItem')->findOneById($childArr['id']);
        $child->getNode()->insertAsLastChildOf($parent);
        $this->restoreBranchFromNestedArray($childArr);
        $parent->refresh();
      }
    }

    return $parent;
  }

  /**
   * Whether or not this class implements I18n
   *
   * @return boolean
   */
  public function isI18n()
  {
    return $this->hasTemplate('Doctrine_Template_I18n');
  }
}