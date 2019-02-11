<?php
/**
 * An example of a project-specific implementation.
 *
 * After registering this autoload function with SPL, the following line
 * would cause the function to attempt to load the \Foo\Bar\Baz\Qux class
 * from /path/to/project/src/Baz/Qux.php:
 *
 *      new \Foo\Bar\Baz\Qux;
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function ($class) {
    // project-specific namespace prefix
    // 项目的命名空间前缀
    $prefix = 'Vendor\\Parse\\';

    // base directory for the namespace prefix
    // 命名空间前缀对应的base目录
    $base_dir = __DIR__ . '/src/';

    // does the class use the namespace prefix?
    // 检查$class中是否包含命名空间前缀
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        // 未包含，立即返回
        return;
    }

    // get the relative class name
    // 获取相对类名
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    // 用base目录替代命名空间前缀,
    // 在相对类名中用目录分隔符'/'来替换命名空间分隔符'\',
    // 并在后面追加.php组成$file的绝对路径
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    // 如果文件存在，则通过require关键字包含文件
    if (file_exists($file)) {
        require $file;
    }
});