.. include:: ../Includes.txt


.. _administration_manual:

Administration Manual
=====================

Target group: **Administrators**

.. _installation:

Installation
^^^^^^^^^^^^

.. _add_via_composer:

Add via composer.json:
----------------------

.. code-block:: javascript

  composer require "autodudes/ai-suite"

- Install the extension via composer
- Flush TYPO3 and PHP Cache
- Add your AutoDudes license key to the extension configuration before using the extension
- If EXT:News is used: Add a page uid wehre the news detail page can be rendered

.. _add_via_ter:

Add via TER:
------------

If you want to install the extension via TER you can find detailed instructions `here <https://docs.typo3.org/m/typo3/guide-installation/10.4/en-us/ExtensionInstallation/Index.html>`_.

- Install the extension via TER
- Flush TYPO3 and PHP Cache
- Add your AutoDudes license key to the extension configuration before using the extension
- If EXT:News is used: Add a page uid wehre the news detail page can be rendered

.. _add_further_information:

Further information
-------------------

The different ways to install an extension and additional detailed information can be found `here <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/HowTo/ExtensionManagement.html>`_.


.. _configuration:

Configuration
^^^^^^^^^^^^^

Extension configuration
-------------------------------

There are the following extension settings available.

.. code-block:: none

   # cat=API Key; type=string; label=AI Suite license Key
   aiSuiteApiKey = YOUR_AI_SUITE_LICENSE_KEY

Enter your generated API key or generate a new one under `https://www.autodudes.de <https://www.autodudes.de>`_

.. code-block:: none

   # cat=API Key; type=string; label=AI Suite server
   aiSuiteServer = https://api.autodudes.de/

Enter the server url to access the AI Suite server (default: https://api.autodudes.de/)

.. code-block:: none

    # cat=OpenAI request settings; type=boolean; label=Use always URL for requests
    useUrlForRequest = 0

With this option you can use the corresponding URL of the page for all analyses. As a result, you have to use fewer tokens to carry out your corresponding analyses. IMPORTANT: The page must be publicly accessible (hidden pages fail and pages in a local environment lead to poor results)

.. code-block:: none

    # cat=OpenAI request settings; type=string; label=Enter the ID of one page that is used as a detail view for news articles of the news extension
    singleNewsDisplayPage =

Enter the page uid of one page that is used as a detail view for news articles of EXT:news. This page will be used to generate the `BackendPreviewUrl`.
