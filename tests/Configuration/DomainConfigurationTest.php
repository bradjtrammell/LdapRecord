<?php

namespace LdapRecord\tests\Configuration;

use LdapRecord\Tests\TestCase;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Configuration\ConfigurationException;

class DomainConfigurationTest extends TestCase
{
    public function test_getting_options()
    {
        $config = new DomainConfiguration();
        $this->assertEmpty($config->get('username'));
    }

    public function test_setting_options()
    {
        $config = new DomainConfiguration();
        $config->set('username', 'foo');
        $this->assertEquals('foo', $config->get('username'));
    }

    public function test_default_options()
    {
        $config = new DomainConfiguration();

        $this->assertEquals(389, $config->get('port'));
        $this->assertEquals([], $config->get('hosts'));
        $this->assertEquals(0, $config->get('follow_referrals'));
        $this->assertEmpty($config->get('username'));
        $this->assertEmpty($config->get('password'));
        $this->assertEmpty($config->get('base_dn'));
        $this->assertFalse($config->get('use_ssl'));
        $this->assertFalse($config->get('use_tls'));
        $this->assertEquals([], $config->get('options'));
    }

    public function test_all_options()
    {
        $config = new DomainConfiguration([
            'port'             => 500,
            'base_dn'          => 'dc=corp,dc=org',
            'hosts'            => ['dc1', 'dc2'],
            'follow_referrals' => false,
            'username'         => 'username',
            'password'         => 'password',
            'use_ssl'          => true,
            'use_tls'          => false,
            'options'          => [
                LDAP_OPT_SIZELIMIT => 1000,
            ],
        ]);

        $this->assertEquals(500, $config->get('port'));
        $this->assertEquals('dc=corp,dc=org', $config->get('base_dn'));
        $this->assertEquals(['dc1', 'dc2'], $config->get('hosts'));
        $this->assertEquals('username', $config->get('username'));
        $this->assertEquals('password', $config->get('password'));
        $this->assertTrue($config->get('use_ssl'));
        $this->assertFalse($config->get('use_tls'));
        $this->assertEquals(
            [
                LDAP_OPT_SIZELIMIT => 1000,
            ],
            $config->get('options')
        );
    }

    public function test_invalid_port()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['port' => 'invalid']);
    }

    public function test_invalid_base_dn()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['base_dn' => ['invalid']]);
    }

    public function test_invalid_domain_controllers()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['hosts' => 'invalid']);
    }

    public function test_invalid_admin_username()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['username' => ['invalid']]);
    }

    public function test_invalid_password()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['password' => ['invalid']]);
    }

    public function test_invalid_follow_referrals()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['follow_referrals' => 'invalid']);
    }

    public function test_invalid_use_ssl()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['use_ssl' => 'invalid']);
    }

    public function test_invalid_use_tls()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['use_tls' => 'invalid']);
    }

    public function test_invalid_options()
    {
        $this->expectException(ConfigurationException::class);

        new DomainConfiguration(['options' => 'invalid']);
    }
}
