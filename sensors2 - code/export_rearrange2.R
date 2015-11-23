
setwd("C:/Users/lab3/Dropbox/htdocs/sensors2")
getwd()

library(foreign)
#library(scales)
#install.packages("sets")
#library(sets)

insertRow <- function(existingDF, newrow, r) { # see http://stackoverflow.com/questions/11561856/add-new-row-to-dataframe
  existingDF[seq(r+1,nrow(existingDF)+1),] <- existingDF[seq(r,nrow(existingDF)),]
  existingDF[r,] <- newrow
  existingDF
}

f="temp_export.csv"
h = read.csv(f); head(h)

### convert everything to numbers (except for time)
asNumeric <- function(x) as.numeric(as.character(x))
factorsNumeric <- function(d) modifyList(d, lapply(d[, sapply(d, is.factor)],asNumeric))
b <- factorsNumeric(h); tail(b)
b$DateTime = h$DateTime # except for the time column

### separate label names and units
cnames = colnames(b); cnames
labels=strsplit(cnames,"[.]"); labels

newcolnames = c()
unitnames = c()
for(thislabel in labels) {
  #print(thislabel[1])
  newcolnames = rbind(newcolnames,thislabel[1])
  unitnames = rbind(unitnames,thislabel[3]) 
}
colnames(b) = newcolnames; newcolnames
unitnames # now we have a vector of colnames and unit names separately

#######################################################################
################################ apply offsets, calculations etc here:

#b$TempHeatflow = b$TempHeatflow-0.4
#b$R_sample = b$U_sample/b$I_sample # EDIT FM
#b$R_sample[b$U_ps <= 1] = -1 # EDIT FM
#b$Control = round(b$Control,0)

#######################################################################
#######################################################################

### add row with unitnames
head(b)
bchar = b # convert to characters
bchar[] <- lapply(bchar, as.character)

unitnames # insert unit names below row labels
bchar = insertRow(bchar,unitnames,1); head(bchar)

### change order of columns
b2 =  data.frame(Time=c("s",1:(nrow(bchar)-1))) # start with a 1 column dataframe
head(b2); tail(b2); tail(bchar)

b2 = cbind(b2,bchar$DateTime) # add just the Datetime

mapping = read.csv("current_mapping.csv",stringsAsFactors=F)

for (i in 1:nrow(mapping)) { # now add more columns based on order in the mapping file
  #print(mapping$ExportName[i])
  if (mapping$ExportName[i] != "" & (mapping$ExportName[i] %in% colnames(bchar))) {
    print("adding row ")
    print(mapping$ExportName[i])
    b2 = cbind(b2,bchar[,mapping$ExportName[i]])
    colnames(b2)[ncol(b2)] = mapping$ExportName[i]
  }
}

# then add all the ones that werent in the csv
notinmappingfile = !(colnames(bchar) %in% mapping$ExportName)
leftovers = colnames(bchar)[notinmappingfile]; leftovers
leftovers = leftovers[leftovers != "DateTime"]; leftovers # remove DateTime

for (i in 1:length(leftovers)) { # now add more columns based on order in the mapping file
  #print(leftovers[i])
  if (leftovers[i] != "") {
    print("adding row ")
    print(leftovers[i])    
    b2 = cbind(b2,bchar[,leftovers[i]])
    colnames(b2)[ncol(b2)] = leftovers[i]
  }
}

head(b2)
#head(bchar)
#b2 = cbind(b2,bchar$Target,bchar$U_r) # determine order here

names(b2) <- sub("bchar\\$", "", names(b2) ); head(b2)

### export to csv
head(b2)
write.csv(b2, file = paste("processed_",f,sep=""),row.names=FALSE) #
write.csv(mapping, file = paste("mapping_",f,sep=""),row.names=FALSE)

