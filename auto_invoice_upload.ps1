# Property of SW and RJ Prestidge, info@prescohire.co.nz
# Author: Seth Tetley-Jones, sethtetleyjones@gmail.com
# February 2016

# arguments: an array of file paths.
# PostInvoices POSTs PDF files stored at these paths via HTTP
# The function assumes that all files paths in the input array
# point to PDFs.  You'll notice that this script checks this
# validity before calling PostInvoices.
function PostInvoices ($invoices) {
	
	# create a boundary between the multipart/formdata items
	# this boundary is arbitrary but should be fairly unique
	$ticks = (Get-Date).ticks.ToString("x")
	$boundary = "---------------------------$ticks"

	# internal URL used for development
	$URI = "http://luella-pc.network.local:8001/post_invoice.php"

	$request = [System.Net.HttpWebRequest]::Create($URI)
	# for the HTTP request, the boundary is defined in the request header
	$request.ContentType = "multipart/form-data; boundary=$boundary";
	$request.Method = "POST"
	$request.KeepAlive = $true
	$request.Credentials = [System.Net.CredentialCache]::DefaultCredentials

	$reqStr = $request.getRequestStream()
	
	# The request body is appended using a StreamWriter object
	$streamWriter = New-Object System.IO.StreamWriter($reqStr)

	# whenever the boundary is used, it must be prepended with "--"
	$streamWriter.Write("`r`n--$boundary`r`n")

	$totalLength = 0
	# We use a for loop, not a foreach, on the array of invoices.  In the case where
	# the request payload has a particular size limit, the for loop makes it simpler to
	# split the array of invoices into subarrays if the total array size exceeds the 
	# size limit.
	for ($i=0
	     $i -lt $invoices.length
	     $i++) {
		     
		     $filename = [System.IO.Path]::GetFileName($invoices[$i])
		     
		     $header = "Content-Disposition: form-data; name=`"invoice_pdf[]`"; filename=`"$filename`"`r`n`Content-Type: application/pdf`r`n`r`n"
		     
		     $fileContent = [IO.File]::ReadAllBytes($invoices[$i])

		     $totalLength += $fileContent.length
		     
		     # In order to send the file over the web, we must encode it in Base64.
		     $fileEnc = [Convert]::ToBase64String($fileContent)

		     # Uncomment the section below if the server sets a limit
		     # on request size.  PostInvoices will be recursively
		     # called on subarrays of the current array of files.
		     #
		     # Note that $totalLength only counts the content of the files,
		     # HTTP headers and other metadata are not included; therefore,
		     # the actual request size will be slightly larger than $totalLength.
		     #
		     #if ($totalLength -gt 11048576) {
		     #   PostInvoices($invoices[0..($i-1)])
		     #  PostInvoices($invoices[$i..($invoices.length)])
		     #  return
		     #}
		     
		     $streamWriter.Write("`r`n--$boundary`r`n")
		     $streamWriter.Write($header)
		     
		     $streamWriter.Write($fileEnc)

		     # Delete the file stored at the specified path
		     Remove-Item $invoices[$i]
	     }

	$streamWriter.Write("`r`n--$boundary`r`n")

	$streamWriter.close()

	# this is where we POST the request
	$res = $request.GetResponse()
	
	#   The following lines print the response to console.
	#   The lines are commented out because the script is 
	#   intended to run silently.
	#
	#	$stream = $res.GetResponseStream()
	#
	#	$streamReader = New-Object System.IO.StreamReader($stream)
	#
	#	$rl = $streamReader.ReadLine()
	#
	#	while($rl -ne $null) {
	#		$rl = $streamReader.ReadLine()
	#         echo $rl
	#	}
	#
	#	$streamReader.close()

	# end function
}




########## THIS IS THE MAIN ENTRY POINT TO THE SCRIPT ##########

# We're only interested in files with the extension ".pdf"
$watcher = New-Object System.IO.FileSystemWatcher "C:\invoices", "*.pdf"

$action = {
	$path = $Event.SourceEventArgs.FullPath
	# variables within an Event Action have extremely limited scope, so we write
	# to a text file to extract the information we need.
	Add-content "C:\inetpub\invoice_program_files\temp_storage.txt" -value $path
}

$created = Register-ObjectEvent $watcher "Created" -Action $action

# The skip variable exists to combat speed problems with that occur
# when extracting large files from file systems.  It's a tad hacky.
$skip = $true

# main program loop
while ($true) {
	sleep 10

	$file = Get-Content "C:\inetpub\invoice_program_files\temp_storage.txt"

	if ($file -eq $null) {continue}

	$paths = @()
	
	# Consider this foreach as a study in the difference between
	# the break and continue keywords.
	foreach ($l in $file) {
    		
		$continue = $false
		# We have to check for duplicates, becuase FileSystemWatcher
		# sometimes detects the same event multiple times.
		foreach ($path in $paths) {
			if ($l -eq $path) {
				$continue = $true
				break
			}
		}
		if ($continue) {continue}

		$paths += $l
	}
	
	if ($paths.length -gt 0) {
		# Some files take a while to save in the file system, so when
		# a file is created in the file system, we skip a turn to give
		# the file by saved.
		if ($skip) {
			$skip = $false
			continue
		}
		$skip = $true
		PostInvoices($paths)
		# Wipe the text file so that we don't attempt to upload the invoices again.
		Clear-Content "C:\inetpub\invoice_program_files\temp_storage.txt"
	}
}