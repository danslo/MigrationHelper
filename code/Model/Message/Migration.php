<?php

/*
 * Copyright 2011 Daniel Sloof
 * http://www.rubic.nl/
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

/**
 * Migration message stored in admin session.
 *
 * @author Daniel Sloof <daniel@rubic.nl>
 */
class Rubic_MigrationHelper_Model_Message_Migration
    extends Mage_Core_Model_Message_Abstract
{

    const MESSAGE_TYPE = 'migration';

    /**
     * Initializes a migration message.
     *
     * @param string $code
     * @return void
     */
    public function __construct($code)
    {
        return parent::__construct(self::MESSAGE_TYPE, $code);
    }

}
