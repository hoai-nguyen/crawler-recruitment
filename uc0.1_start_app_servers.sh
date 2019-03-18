# start n instances of application.

num=$1
echo "Your input: "$1

re='^[0-9]+$'

if ! [[ $num =~ $re ]] ; then
	echo "error: Not a number"
	echo "error: Enter a number in [1,9]" >&2; exit 1
fi

if [[ $num -gt 0 && $num -lt 10 ]]; then 
	echo $num
	for i in $( eval echo {1..$num} )
	do
		echo "Server number: "$i
		gnome-terminal -- /bin/bash -c "php artisan serve --port=8$i$i$i"
	done
	
else
	echo "error: Enter a number in [1,9]" >&2; exit 1
   
fi

#gnome-terminal -- /bin/bash -c "php artisan serve --port=8111"

#gnome-terminal -- /bin/bash -c "php artisan serve --port=8222"

#gnome-terminal -- /bin/bash -c "php artisan serve --port=8333"

