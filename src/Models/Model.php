<?php

namespace LdapRecord\Models;

use ArrayAccess;
use JsonSerializable;
use LdapRecord\Container;
use LdapRecord\Utilities;
use InvalidArgumentException;
use LdapRecord\LdapInterface;
use UnexpectedValueException;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Attributes\MbString;

/** @mixin Builder */
abstract class Model implements ArrayAccess, JsonSerializable
{
    use Concerns\HasEvents,
        Concerns\HasScopes,
        Concerns\HasAttributes,
        Concerns\HasRelationships;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The models distinguished name.
     *
     * @var string|null
     */
    protected $dn;

    /**
     * The base DN of where the model should be created in.
     *
     * @var string|null
     */
    protected $in;

    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [];

    /**
     * The current LDAP connection to utilize.
     *
     * @var string
     */
    protected $connection = 'default';

    /**
     * The attribute key that contains the Object GUID.
     *
     * @var string
     */
    protected $guidKey = 'objectguid';

    /**
     * Contains the models modifications.
     *
     * @var array
     */
    protected $modifications = [];

    /**
     * Constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static())->$method(...$parameters);
    }

    /**
     * Returns the models distinguished name.
     *
     * @return string|null
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Set the models distinguished name.
     *
     * @param string $dn
     *
     * @return static
     */
    public function setDn($dn)
    {
        $this->dn = (string) $dn;

        return $this;
    }

    /**
     * Get the LDAP connection for the model.
     *
     * @return \LdapRecord\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->connection);
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setConnection($name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param string|null $connection
     *
     * @return Builder
     */
    public static function on($connection = null)
    {
        $instance = new static();

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Begin querying the model.
     *
     * @return Builder
     */
    public static function query()
    {
        return (new static())->newQuery();
    }

    /**
     * Get a new query for builder filtered by the current models object classes.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return $this->registerModelScopes(
            $this->newQueryWithoutScopes()
        );
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function newQueryWithoutScopes()
    {
        $connection = static::resolveConnection($this->connection);

        return $connection->query()->model($this);
    }

    /**
     * Create a new query builder.
     *
     * @param LdapInterface $connection
     *
     * @return Builder
     */
    public function newQueryBuilder(LdapInterface $connection)
    {
        return new Builder($connection);
    }

    /**
     * Resolve a connection instance.
     *
     * @param string|null $connection
     *
     * @return \LdapRecord\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return Container::getInstance()->get($connection);
    }

    /**
     * Register the query scopes for this builder instance.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function registerModelScopes($builder)
    {
        $this->applyObjectClassScopes($builder);

        return $builder;
    }

    /**
     * Apply the model object class scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyObjectClassScopes(Builder $query)
    {
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
    }

    /**
     * Returns the models distinguished name when the model is converted to a string.
     *
     * @return null|string
     */
    public function __toString()
    {
        return $this->getDn();
    }

    /**
     * Returns a new batch modification.
     *
     * @param string|null     $attribute
     * @param string|int|null $type
     * @param array           $values
     *
     * @return BatchModification
     */
    public function newBatchModification($attribute = null, $type = null, $values = [])
    {
        return new BatchModification($attribute, $type, $values);
    }

    /**
     * Returns a new collection with the specified items.
     *
     * @param mixed $items
     *
     * @return Collection
     */
    public function newCollection($items = [])
    {
        return new Collection($items);
    }

    /**
     * Dynamically retrieve attributes on the object.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the object.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return $this
     */
    public function __set($key, $value)
    {
        return $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return !is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value at the given offset.
     *
     * @param string $offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $attributes = $this->getAttributes();

        array_walk_recursive($attributes, function (&$val) {
            if (MbString::isLoaded()) {
                // If we're able to detect the attribute
                // encoding, we'll encode only the
                // attributes that need to be.
                if (!MbString::isUtf8($val)) {
                    $val = utf8_encode($val);
                }
            } else {
                // If the mbstring extension is not loaded, we'll
                // encode all attributes to make sure
                // they are encoded properly.
                $val = utf8_encode($val);
            }
        });

        return $this->convertAttributesForJson($attributes);
    }

    /**
     * Converts attributes for JSON serialization.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function convertAttributesForJson(array $attributes = [])
    {
        if ($this->hasAttribute($this->guidKey)) {
            // If the model has a GUID set, we need to convert it due to it being in
            // binary. Otherwise we will receive a JSON serialization exception.
            return array_replace($attributes, [
                $this->guidKey => [$this->getConvertedGuid()],
            ]);
        }

        return $attributes;
    }

    /**
     * Reload a fresh model instance from the directory.
     *
     * @return static|false
     */
    public function fresh()
    {
        if (!$this->exists) {
            return false;
        }

        return $this->newQuery()->find($this->dn);
    }

    /**
     * Determine if two models have the same distinguished name and belong to the same connection.
     *
     * @param static $model
     *
     * @return bool
     */
    public function is(self $model)
    {
        return $this->dn == $model->getDn() && $this->connection == $model->getConnectionName();
    }

    /**
     * Hydrate a new collection of models from LDAP search results.
     *
     * @param array $records
     *
     * @return Collection
     */
    public function hydrate($records)
    {
        return $this->newCollection($records)->transform(function ($attributes) {
            return (new static())->setRawAttributes($attributes)->setConnection($this->getConnectionName());
        });
    }

    /**
     * Converts the current model into the given model.
     *
     * @param static $into
     *
     * @return Model
     */
    public function convert(self $into)
    {
        $into->setDn($this->getDn());
        $into->setConnection($this->getConnectionName());

        $this->exists ?
            $into->setRawAttributes($this->getAttributes()) :
            $into->fill($this->getAttributes());

        return $into;
    }

    /**
     * Synchronizes the current models attributes with the directory values.
     *
     * @return bool
     */
    public function synchronize()
    {
        if ($model = $this->fresh()) {
            $this->setRawAttributes($model->getAttributes());

            return true;
        }

        return false;
    }

    /**
     * Get the models batch modifications to be processed.
     *
     * @return array
     */
    public function getModifications()
    {
        $this->buildModificationsFromDirty();

        return $this->modifications;
    }

    /**
     * Set the models modifications array.
     *
     * @param array $modifications
     *
     * @return $this
     */
    public function setModifications(array $modifications = [])
    {
        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        return $this;
    }

    /**
     * Adds a batch modification to the models modifications array.
     *
     * @param array|BatchModification $mod
     *
     * @throws InvalidArgumentException
     *
     * @return $this
     */
    public function addModification($mod = [])
    {
        if ($mod instanceof BatchModification) {
            $mod = $mod->get();
        }

        if ($this->isValidModification($mod)) {
            $this->modifications[] = $mod;

            return $this;
        }

        throw new InvalidArgumentException(
            "The batch modification array does not include the mandatory 'attrib' or 'modtype' keys."
        );
    }

    /**
     * Get the models guid attribute key name.
     *
     * @return string
     */
    public function getGuidKey()
    {
        return $this->guidKey;
    }

    /**
     * Get the models ANR attributes for querying when incompatible with ANR.
     *
     * @return array
     */
    public function getAnrAttributes()
    {
        return ['cn', 'sn', 'uid', 'name', 'mail', 'givenname', 'displayname'];
    }

    /**
     * Get the models RDN.
     *
     * @return string|null
     */
    public function getRdn()
    {
        if ($parts = Utilities::explodeDn($this->dn, false)) {
            return array_key_exists(0, $parts) ? $parts[0] : null;
        }
    }

    /**
     * Get the parent distinguished name of the given.
     *
     * @param static|string
     *
     * @return string|null
     */
    public function getParentDn($dn)
    {
        if ($parts = Utilities::explodeDn($dn, false)) {
            array_shift($parts);

            return implode(',', $parts);
        }
    }

    /**
     * Get the models binary object GUID.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679021(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getObjectGuid()
    {
        return $this->getFirstAttribute($this->guidKey);
    }

    /**
     * Get the models string GUID.
     *
     * @return string|null
     */
    public function getConvertedGuid()
    {
        try {
            return (string) new Guid($this->getObjectGuid());
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Determine if the current model is a direct descendant of the given.
     *
     * @param static|string $model
     *
     * @return bool
     */
    public function isDescendantOf($model)
    {
        return $this->dnIsInside($this->getDn(), $model);
    }

    /**
     * Determine if the current model is a direct ancestor of the given.
     *
     * @param static|string $model
     *
     * @return bool
     */
    public function isAncestorOf($model)
    {
        return $this->dnIsInside($model, $this->getDn());
    }

    /**
     * Determines if the DN is inside of the parent DN.
     *
     * @param static|string $dn
     * @param static|string $parentDn
     *
     * @return bool
     */
    protected function dnIsInside($dn, $parentDn)
    {
        if (!$dn) {
            return false;
        }

        if ($dn = $this->getParentDn($dn)) {
            return strtolower($dn) == strtolower($parentDn);
        }

        return false;
    }

    /**
     * Set the base DN of where the model should be created in.
     *
     * @param static|string $dn
     *
     * @return $this
     */
    public function inside($dn)
    {
        $this->in = $dn instanceof self ? $dn->getDn() : $dn;

        return $this;
    }

    /**
     * Save the model to the directory.
     *
     * @param array $attributes The attributes to update or create for the current entry.
     *
     * @return bool
     */
    public function save(array $attributes = [])
    {
        $this->fireModelEvent(new Events\Saving($this));

        $saved = $this->exists ? $this->update($attributes) : $this->create($attributes);

        if ($saved) {
            $this->fireModelEvent(new Events\Saved($this));
        }

        return $saved;
    }

    /**
     * Create the model in the directory.
     *
     * @param array $attributes The attributes for the new entry.
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function create(array $attributes = [])
    {
        $this->fill($attributes);

        // Here we will populate the models object class if it
        // does not already have one. An LDAP record
        // cannot be created without it.
        if (!$this->hasAttribute('objectclass')) {
            $this->setAttribute('objectclass', static::$objectClasses);
        }

        $query = $this->newQuery();

        // If the model doesn't currently have a distinguished
        // name set, we'll create one automatically using
        // the current query builders base DN.
        if (empty($this->dn)) {
            $this->dn = $this->getCreatableDn();
        }

        $this->fireModelEvent(new Events\Creating($this));

        // Before performing the insert, we will filter the attributes of the model
        // to ensure no empty values are sent. If empty values are sent, then
        // the LDAP server will return an error message indicating such.
        if ($query->insert($this->dn, array_filter($this->getAttributes()))) {
            $this->fireModelEvent(new Events\Created($this));

            $this->exists = true;

            return $this->synchronize();
        }

        return false;
    }

    /**
     * Create an attribute on the model.
     *
     * @param string $attribute The attribute to create
     * @param mixed  $value     The value of the new attribute
     * @param bool   $sync      Whether to re-sync all attributes
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function createAttribute($attribute, $value, $sync = true)
    {
        $this->validateExistence();

        if ($this->newQuery()->insertAttributes($this->dn, [$attribute => (array) $value])) {
            return $sync ? $this->synchronize() : true;
        }

        return false;
    }

    /**
     * Update the model.
     *
     * @param array $attributes The attributes to update for the current entry.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function update(array $attributes = [])
    {
        $this->validateExistence();

        $this->fill($attributes);

        $modifications = $this->getModifications();

        if (count($modifications) > 0) {
            $this->fireModelEvent(new Events\Updating($this));

            if ($this->newQuery()->update($this->dn, $modifications)) {
                $this->fireModelEvent(new Events\Updated($this));

                // Re-set the models modifications.
                $this->modifications = [];

                // Re-sync the models attributes.
                return $this->synchronize();
            }

            return false;
        }

        return true;
    }

    /**
     * Update the model attribute with the specified value.
     *
     * @param string $attribute The attribute to modify
     * @param mixed  $value     The new value for the attribute
     * @param bool   $sync      Whether to re-sync all attributes
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function updateAttribute($attribute, $value, $sync = true)
    {
        $this->validateExistence();

        if ($this->newQuery()->updateAttributes($this->dn, [$attribute => (array) $value])) {
            return $sync ? $this->synchronize() : true;
        }

        return false;
    }

    /**
     * Destroy the models for the given distinguished names.
     *
     * @param Collection|array|string $dns
     * @param bool                    $recursive
     *
     * @return int
     */
    public static function destroy($dns, $recursive = false)
    {
        $count = 0;

        if ($dns instanceof Collection) {
            $dns = $dns->all();
        }

        $dns = is_array($dns) ? $dns : func_get_args();

        $instance = new static();

        foreach ($dns as $dn) {
            $model = $instance->find($dn);

            if ($model && $model->delete($recursive)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete the model from the directory.
     *
     * Throws a ModelNotFoundException if the current model does
     * not exist or does not contain a distinguished name.
     *
     * @param bool $recursive Whether to recursively delete leaf nodes (models that are children).
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function delete($recursive = false)
    {
        $this->validateExistence();

        $this->fireModelEvent(new Events\Deleting($this));

        if ($recursive) {
            $this->deleteLeafNodes();
        }

        if ($this->newQuery()->delete($this->dn)) {
            // If the deletion was successful, we'll mark the model
            // as non-existing and fire the deleted event.
            $this->exists = false;

            $this->fireModelEvent(new Events\Deleted($this));

            return true;
        }

        return false;
    }

    /**
     * Deletes leaf nodes that are attached to the model.
     *
     * @return Collection
     */
    protected function deleteLeafNodes()
    {
        return $this->newQuery()->listing()->in($this->dn)->get()->each(function (self $model) {
            $model->delete(true);
        });
    }

    /**
     * Delete an attribute on the model.
     *
     * @param string|array $attributes The attribute(s) to delete
     * @param bool         $sync       Whether to re-sync all attributes
     *
     * Delete specific values in attributes:
     *
     *     ["memberuid" => "username"]
     *
     * Delete an entire attribute:
     *
     *     ["memberuid" => []]
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function deleteAttribute($attributes, $sync = true)
    {
        $this->validateExistence();

        // If we've been given a string, we'll assume we're removing a
        // single attribute. Otherwise, we'll assume it's
        // an array of attributes to remove.
        $attributes = is_string($attributes) ? [$attributes => []] : $attributes;

        if ($this->newQuery()->deleteAttributes($this->dn, $attributes)) {
            return $sync ? $this->synchronize() : true;
        }

        return false;
    }

    /**
     * Move the model into the given new parent.
     *
     * For example: $user->move($ou);
     *
     * @param static|string $newParentDn  The new parent of the current model.
     * @param bool          $deleteOldRdn Whether to delete the old models relative distinguished name once renamed / moved.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function move($newParentDn, $deleteOldRdn = true)
    {
        $this->validateExistence();

        if ($rdn = $this->getRdn()) {
            return $this->rename($rdn, $newParentDn, $deleteOldRdn);
        }

        throw new UnexpectedValueException('Current model does not contain an RDN to move.');
    }

    /**
     * Rename the model to a new RDN and new parent.
     *
     * @param string             $rdn          The models new relative distinguished name. Example: "cn=JohnDoe"
     * @param static|string|null $newParentDn  The models new parent distinguished name (if moving). Leave this null if you are only renaming. Example: "ou=MovedUsers,dc=acme,dc=org"
     * @param bool|true          $deleteOldRdn Whether to delete the old models relative distinguished name once renamed / moved.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function rename($rdn, $newParentDn = null, $deleteOldRdn = true)
    {
        $this->validateExistence();

        if ($newParentDn instanceof self) {
            $newParentDn = $newParentDn->getDn();
        }

        if (is_null($newParentDn)) {
            $newParentDn = $this->getParentDn($this->dn);
        }

        if ($this->newQuery()->rename($this->dn, $rdn, $newParentDn, $deleteOldRdn)) {
            // If the model was successfully renamed, we will set
            // its new DN so any further updates to the model
            // can be performed without any issues.
            $this->dn = implode(',', [$rdn, $newParentDn]);

            return true;
        }

        return false;
    }

    /**
     * Get a distinguished name that is creatable for the model.
     *
     * @return string
     */
    public function getCreatableDn()
    {
        return implode(',', [$this->getCreatableRdn(), $this->in ?? $this->newQuery()->getDn()]);
    }

    /**
     * Get a creatable RDN for the model.
     *
     * @return string
     */
    public function getCreatableRdn()
    {
        return "cn={$this->getFirstAttribute('cn')}";
    }

    /**
     * Determines if the given modification is valid.
     *
     * @param mixed $mod
     *
     * @return bool
     */
    protected function isValidModification($mod)
    {
        return is_array($mod) &&
            array_key_exists(BatchModification::KEY_MODTYPE, $mod) &&
            array_key_exists(BatchModification::KEY_ATTRIB, $mod);
    }

    /**
     * Builds the models modifications from its dirty attributes.
     *
     * @return array
     */
    protected function buildModificationsFromDirty()
    {
        foreach ($this->getDirty() as $attribute => $values) {
            // Make sure values is always an array.
            $values = (is_array($values) ? $values : [$values]);

            // Create a new modification.
            $modification = $this->newBatchModification($attribute, null, $values);

            if (array_key_exists($attribute, $this->original)) {
                // If the attribute we're modifying has an original value, we'll give the
                // BatchModification object its values to automatically determine
                // which type of LDAP operation we need to perform.
                $modification->setOriginal($this->original[$attribute]);
            }

            // Build the modification from its
            // possible original values.
            $modification->build();

            if ($modification->isValid()) {
                // Finally, we'll add the modification to the model.
                $this->addModification($modification);
            }
        }

        return $this->modifications;
    }

    /**
     * Validates that the current model exists.
     *
     * @throws ModelDoesNotExistException
     *
     * @return void
     */
    protected function validateExistence()
    {
        if (!$this->exists || is_null($this->dn)) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }
    }
}
