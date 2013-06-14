<?php

class OWMigrationRoleCodeGenerator extends OWMigrationCodeGenerator {

    static function getMigrationClassFile( $role, $dir ) {
        if( is_numeric( $role ) ) {
            $role = eZRole::fetch( $role );
        }
        if( !$role instanceof eZRole ) {
            return FALSE;
        }
        self::createDirectory( $dir );
        $filename = self::generateSafeFileName( $role->attribute( 'name' ) . 'rolemigration.php' );
        $filepath = $dir . $filename;
        @unlink( $filepath );
        eZFile::create( $filepath, false, OWMigrationRoleCodeGenerator::getMigrationClass( $role ) );
        return $filepath;
    }

    static function getMigrationClass( $role ) {
        if( is_numeric( $role ) ) {
            $role = eZRole::fetch( $role );
        }
        if( !$role instanceof eZRole ) {
            return FALSE;
        }
        $code = "<?php" . PHP_EOL . PHP_EOL;
        $code .= sprintf( "class %sRoleMigration extends OWMigration {" . PHP_EOL, self::generateClassName( $role->attribute( 'name' ) ) );
        $code .= self::getUpMethod( $role );
        $code .= self::getDownMethod( $role );
        $code .= "}" . PHP_EOL . PHP_EOL;
        $code .= "?>";
        return $code;
    }

    static function getUpMethod( $role ) {
        $code = "\tpublic function up( ) {" . PHP_EOL;
        $code .= "\t\t\$migration = new OWMigrationRole( );" . PHP_EOL;
        $code .= sprintf( "\t\t\$migration->startMigrationOn( '%s' );" . PHP_EOL, self::escapeString( $role->attribute( 'name' ) ) );
        $code .= "\t\t\$migration->createIfNotExists( );" . PHP_EOL . PHP_EOL;
        foreach( $role->policyList() as $policy ) {
            $code .= sprintf( "\t\t\$migration->addPolicy( '%s', '%s'", self::escapeString( $policy->attribute( 'module_name' ) ), self::escapeString( $policy->attribute( 'function_name' ) ) );
            $policyLimitationArray = self::getLimitationArray( $policy );
            if( count( $policyLimitationArray ) > 0 ) {
                $code .= ", array(" . PHP_EOL;
                foreach( $policyLimitationArray as $limitationKey => $limitationValue ) {
                    if( is_array( $limitationValue ) ) {
                        $arrayString = "array(\n\t\t\t\t'" . implode( "',\n\t\t\t\t'", $limitationValue ) . "'\n\t\t\t )";
                        $code .= sprintf( "\t\t\t'%s' => %s," . PHP_EOL, self::escapeString( $limitationKey ), $arrayString );
                    } else {
                        $code .= sprintf( "\t\t\t'%s' => '%s'," . PHP_EOL, self::escapeString( $limitationKey ), $limitationValue );
                    }
                }
                $code .= "\t\t) );" . PHP_EOL;
            } else {
                $code .= " );" . PHP_EOL;
            }
            //var_dump( $policy->limitationList( ) );
        }
        $code .= "\t}" . PHP_EOL . PHP_EOL;
        return $code;
    }

    static function getDownMethod( $role ) {
        $code = "\tpublic function down( ) {" . PHP_EOL;
        $code .= "\t\t\$migration = new OWMigrationRole( );" . PHP_EOL;
        $code .= sprintf( "\t\t\$migration->startMigrationOn( '%s' );" . PHP_EOL, self::escapeString( $role->attribute( 'name' ) ) );
        $code .= "\t\t\$migration->removeRole( );" . PHP_EOL;
        $code .= "\t}" . PHP_EOL;
        return $code;
    }

    static function getLimitationArray( $policy ) {
        $returnValue = array( );
        $names = array( );
        if( !$policy ) {
            return $returnValue;
        }

        $currentModule = $policy->attribute( 'module_name' );
        $mod = eZModule::exists( $currentModule );
        if( !is_object( $mod ) ) {
            eZDebug::writeError( 'Failed to fetch instance for module ' . $currentModule );
            return $returnValue;
        }
        $functions = $mod->attribute( 'available_functions' );
        $functionNames = array_keys( $functions );

        $currentFunction = $policy->attribute( 'function_name' );

        foreach( $policy->limitationList() as $limitation ) {
            $valueList = $limitation->attribute( 'values_as_array' );
            $limitation = $functions[$currentFunction][$limitation->attribute( 'identifier' )];
            $limitationValueArray = array( );

            switch( $limitation['name'] ) {
                case 'Class' :
                    foreach( $valueList as $value ) {
                        $contentClass = eZContentClass::fetch( $value, false );
                        if( $contentClass != null ) {
                            $limitationValueArray[] = $contentClass['identifier'];
                        }
                    }
                    break;
                case 'Node' :
                case 'Subtree' :
                    $limitationValueArray = $valueList;
                    break;
                default :
                    if( $limitation && isset( $limitation['class'] ) && count( $limitation['values'] ) == 0 ) {
                        $obj = new $limitation['class']( array( ) );
                        $limitationValueList = call_user_func_array( array(
                            $obj,
                            $limitation['function']
                        ), $limitation['parameter'] );
                        foreach( $limitationValueList as $limitationValue ) {
                            $limitationValueArray[] = $limitationValue['name'];
                        }
                    } else {
                        $limitationValueArray = $valueList;
                    }
                    break;
            }
            $returnValue[$limitation['name']] = $limitationValueArray;
        }
        return $returnValue;
    }

}
?>