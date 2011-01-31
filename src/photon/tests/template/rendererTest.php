<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


namespace photon\tests\template\rendererTest;

use photon\template as template;
use \photon\config\Container as Conf;

class rendererTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->conf = Conf::dump();
    }

    public function tearDown()
    {
        Conf::load($this->conf);
    }

    public function testSimpleRenderer()
    {
        $renderer = new template\Renderer('data-template-simplest.html', 
                                          array(__dir__));
        $this->assertequals('Hello World!'."\n", $renderer->render());
    }

    public function testExampleTag()
    {
        $renderer = new template\Renderer('data-template-exampletag.html', 
                                          array(__dir__), null,
                                          array('tags' => 
                                                array('example' => '\\photon\\template\\tag\\Example')));
        $this->assertequals('Param1: , param2: foo<pre>Start: foo</pre>BarParam1: end foo'."\n", $renderer->render());
    }

    public function testExampleTagUrl()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home$#',
                                      'view' => array('\helloworld\views\Views', 'you'),
                                      'name' => 'home',
                                      ),
                                ));
        $renderer = new template\Renderer('data-template-tag-url.html', 
                                          array(__dir__));
        $this->assertequals('/home'."\n", $renderer->render());
    }
}
