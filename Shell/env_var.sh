########自动添加环境变量#####
##格式设定为##   参数名=参数值 ##
#############################
##如果参数环境变量是:export 打头的使用下面的方法注销变量
## unset_export 文件名
##下面命令使用方法: set_env_var 文件名## 即可
##注意alias所在行最后面有空格

alias set_env_var  =sed -n 's/^/export /' 
alias unset_env_var = sed -n  's/=.*//;s/^/unset /' 
unset_export(){
	sed -n 's/=.*//;s/export/unset/p' $1 >> source
}
#接下来要做的是，只用一个函数，支持多文件注册，删除:env-var -s|-u file1 file2 ...
#支持俩种文件格式 export a=bbb 和 a=bbb 都可执行，混合执行也可 
