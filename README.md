HVRawConnectorPHP [![Build Status](https://travis-ci.org/communitychair1/HVRawConnectorPHP.png?branch=master)](https://travis-ci.org/communitychair1/HVRawConnectorPHP)
=================

Simple PHP library to connect to
[Microsoft HealthVault](https://www.healthvault.com/).

This library is a fork of [HVRawConnectorPHP](https://github.com/mkalkbrenner/HVRawConnectorPHP/). Several changes have been made to the library since the fork and they are documented at the bottom of this document.


Installation
------------

HVRawConnectorPHP depends on some PHP libraries. If you simply use the latest
development version of HVRawConnectorPHP from github you have to ensure that
all of them are installed. You can use pear to do so:

    pear channel-discover pear.querypath.org
    pear install querypath/QueryPath
    pear install Log
    pear install Net_URL2

HVRawConnectorPHP itself could be installed by pear, too. In that case all the
dependencies mentioned above will be installed automatically:

    pear channel-discover pear.biologis.com
    pear channel-discover pear.querypath.org
    pear install biologis/HVRawConnector

This method will install HVRawConnectorPHP as a library, but without the
available demo application.


Usage
-----

Some examples will follow later.

Meanwhile you can have a look at the demo_app source code.


Demo
----

The demo_app included in this repository queries a user's HealthVault record
for all "[Things](http://developer.healthvault.com/pages/types/types.aspx)" and
dumps the content. By default it uses the US pre production instance of
HealthVault.

Simply put the HVRawConnectorPHP folder on a web server and access
"demo_app/index.php".

Changes and Additions
---------------------

- Updated the GetThings method to be able to make both online and offline requests


Licence
-------

[GPLv2](https://raw.github.com/communitychair1/HVRawConnectorPHP/master/LICENSE.txt).
